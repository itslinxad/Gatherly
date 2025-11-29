<?php
session_start();

// Ensure user is logged in and is an organizer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'organizer') {
    header("Location: ../signin.php");
    exit();
}

require_once '../../../src/services/dbconnect.php';

$first_name = $_SESSION['first_name'] ?? 'Organizer';
$user_id = $_SESSION['user_id'];

// Fetch ALL events (bookings) for this organizer, newest first
$query = "
    SELECT 
        e.event_id,
        e.event_name,
        e.event_type,
        e.event_date,
        e.status,
        e.total_cost,
        e.total_paid,
        e.payment_status,
        v.venue_name,
        CONCAT(l.city, ', ', l.province) as location
    FROM events e
    LEFT JOIN venues v ON e.venue_id = v.venue_id
    LEFT JOIN locations l ON v.location_id = l.location_id
    WHERE e.organizer_id = ?
    ORDER BY e.created_at DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings | Gatherly</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/logo.png">
    <link rel="stylesheet"
        href="../../../src/output.css?v=<?php echo filemtime(__DIR__ . '/../../../src/output.css'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&family=Montserrat:ital,wght@0,100..900;1,100..900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"
        integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>

<body
    class="<?php echo $nav_layout === 'sidebar' ? 'bg-gray-100' : 'bg-linear-to-br from-indigo-50 via-white to-cyan-50'; ?> font-['Montserrat'] min-h-screen">
    <?php include '../../../src/components/OrganizerSidebar.php'; ?>

    <!-- Main Content -->
    <div
        class="<?php echo $nav_layout === 'sidebar' ? 'lg:ml-64' : 'container mx-auto'; ?> <?php echo $nav_layout === 'sidebar' ? '' : 'px-4 sm:px-6 lg:px-8'; ?> min-h-screen">
        <?php if ($nav_layout === 'sidebar'): ?>
            <!-- Top Bar for Sidebar Layout -->
            <div class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-20 px-4 sm:px-6 lg:px-8 py-4 mb-8">
                <h1 class="text-2xl font-bold text-gray-800">My Bookings</h1>
                <p class="text-sm text-gray-600">Manage all your event bookings and reservations</p>
            </div>
            <div class="px-4 sm:px-6 lg:px-8">
            <?php else: ?>
                <!-- Header for Navbar Layout -->
                <div class="mb-8">
                    <h1 class="mb-2 text-3xl font-bold text-gray-800 sm:text-4xl">My Bookings</h1>
                    <p class="text-gray-600">Manage all your event bookings and reservations</p>
                </div>
            <?php endif; ?>

            <!-- Search & Filter Section -->
            <div class="mb-6 bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <div class="flex flex-col sm:flex-row gap-4">
                    <div class="flex-1">
                        <div class="relative">
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            <input type="text" id="searchInput" placeholder="Search by event name, venue, or location..."
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <select id="statusFilter" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="completed">Completed</option>
                            <option value="canceled">Canceled</option>
                        </select>
                        <select id="sortBy" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="date-desc">Latest First</option>
                            <option value="date-asc">Oldest First</option>
                            <option value="name-asc">Name (A-Z)</option>
                            <option value="name-desc">Name (Z-A)</option>
                            <option value="cost-desc">Cost (High-Low)</option>
                            <option value="cost-asc">Cost (Low-High)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Bookings Table -->
            <?php if ($result && $result->num_rows > 0): ?>
                <?php
                $events = [];
                while ($event = $result->fetch_assoc()) {
                    $events[] = $event;
                }
                ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full" id="bookingsTable">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Event Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Venue</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Location</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Event Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Total Cost</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Payment</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($events as $event): ?>
                                    <tr class="hover:bg-gray-50 transition-colors booking-row"
                                        data-event-name="<?php echo htmlspecialchars($event['event_name']); ?>"
                                        data-venue="<?php echo htmlspecialchars($event['venue_name'] ?? ''); ?>"
                                        data-location="<?php echo htmlspecialchars($event['location'] ?? ''); ?>"
                                        data-status="<?php echo htmlspecialchars($event['status']); ?>"
                                        data-date="<?php echo htmlspecialchars($event['event_date']); ?>"
                                        data-cost="<?php echo $event['total_cost'] ?? 0; ?>">
                                        <td class="px-6 py-4">
                                            <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($event['event_name']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($event['event_type'] ?? 'N/A'); ?></div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            <?php echo htmlspecialchars($event['venue_name'] ?? '—'); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-600">
                                            <?php echo htmlspecialchars($event['location'] ?? '—'); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            <?php echo date('M d, Y', strtotime($event['event_date'])); ?>
                                            <div class="text-xs text-gray-500"><?php echo date('g:i A', strtotime($event['event_date'])); ?></div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full
                                        <?php
                                        switch ($event['status']) {
                                            case 'confirmed':
                                                echo 'bg-green-100 text-green-700';
                                                break;
                                            case 'pending':
                                                echo 'bg-yellow-100 text-yellow-700';
                                                break;
                                            case 'completed':
                                                echo 'bg-blue-100 text-blue-700';
                                                break;
                                            case 'canceled':
                                                echo 'bg-red-100 text-red-700';
                                                break;
                                            default:
                                                echo 'bg-gray-100 text-gray-700';
                                        }
                                        ?>">
                                                <?php echo ucfirst($event['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 font-semibold text-indigo-600">
                                            ₱<?php echo number_format($event['total_cost'] ?? 0, 2); ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php
                                            $total_paid = floatval($event['total_paid'] ?? 0);
                                            $total_cost = floatval($event['total_cost'] ?? 0);
                                            $payment_status = $event['payment_status'] ?? 'unpaid';
                                            ?>
                                            <div class="text-sm">
                                                <div class="font-medium text-gray-900">₱<?php echo number_format($total_paid, 2); ?></div>
                                                <div class="text-xs <?php
                                                                    echo $payment_status === 'paid' ? 'text-green-600' : ($payment_status === 'partial' ? 'text-orange-600' : 'text-red-600');
                                                                    ?>">
                                                    <?php echo ucfirst($payment_status); ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if ($event['status'] === 'confirmed' && $payment_status !== 'paid'): ?>
                                                <button onclick="openPaymentModal(<?php echo $event['event_id']; ?>, <?php echo $total_cost; ?>, <?php echo $total_paid; ?>)"
                                                    class="px-3 py-1.5 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 transition-colors">
                                                    <i class="fas fa-money-bill-wave mr-1"></i> Pay Now
                                                </button>
                                            <?php else: ?>
                                                <span class="text-xs text-gray-400">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-600">
                                Showing <span id="showingCount"><?php echo count($events); ?></span> of <span id="totalCount"><?php echo count($events); ?></span> bookings
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                    const searchInput = document.getElementById('searchInput');
                    const statusFilter = document.getElementById('statusFilter');
                    const sortBy = document.getElementById('sortBy');
                    const tableRows = document.querySelectorAll('.booking-row');
                    const showingCount = document.getElementById('showingCount');
                    const totalCount = document.getElementById('totalCount');

                    function filterAndSortTable() {
                        const searchTerm = searchInput.value.toLowerCase();
                        const statusValue = statusFilter.value.toLowerCase();

                        let visibleRows = Array.from(tableRows).filter(row => {
                            const eventName = row.dataset.eventName.toLowerCase();
                            const venue = row.dataset.venue.toLowerCase();
                            const location = row.dataset.location.toLowerCase();
                            const status = row.dataset.status.toLowerCase();

                            const matchesSearch = eventName.includes(searchTerm) ||
                                venue.includes(searchTerm) ||
                                location.includes(searchTerm);
                            const matchesStatus = !statusValue || status === statusValue;

                            return matchesSearch && matchesStatus;
                        });

                        // Sort visible rows
                        const sortValue = sortBy.value;
                        visibleRows.sort((a, b) => {
                            switch (sortValue) {
                                case 'date-desc':
                                    return new Date(b.dataset.date) - new Date(a.dataset.date);
                                case 'date-asc':
                                    return new Date(a.dataset.date) - new Date(b.dataset.date);
                                case 'name-asc':
                                    return a.dataset.eventName.localeCompare(b.dataset.eventName);
                                case 'name-desc':
                                    return b.dataset.eventName.localeCompare(a.dataset.eventName);
                                case 'cost-desc':
                                    return parseFloat(b.dataset.cost) - parseFloat(a.dataset.cost);
                                case 'cost-asc':
                                    return parseFloat(a.dataset.cost) - parseFloat(b.dataset.cost);
                            }
                        });

                        // Hide all rows first
                        tableRows.forEach(row => row.style.display = 'none');

                        // Show and reorder visible rows
                        const tbody = document.querySelector('#bookingsTable tbody');
                        visibleRows.forEach(row => {
                            row.style.display = '';
                            tbody.appendChild(row);
                        });

                        showingCount.textContent = visibleRows.length;
                    }

                    searchInput.addEventListener('input', filterAndSortTable);
                    statusFilter.addEventListener('change', filterAndSortTable);
                    sortBy.addEventListener('change', filterAndSortTable);
                </script>
            <?php else: ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
                    <div class="text-gray-400 mb-4">
                        <i class="fas fa-ticket-alt text-6xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">No bookings yet</h3>
                    <p class="text-gray-600 mb-6">You haven't created any event bookings.</p>
                    <a href="find-venues.php"
                        class="inline-block px-6 py-3 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition-colors">
                        <i class="fas fa-search mr-2"></i>Find a Venue & Book
                    </a>
                </div>
            <?php endif; ?>
            <?php if ($nav_layout === 'sidebar'): ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Payment Modal -->
    <div id="paymentModal" class="hidden fixed inset-0 z-[9999] overflow-y-auto" aria-labelledby="payment-modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div id="paymentModalBackdrop" class="fixed inset-0 transition-opacity bg-gray-900 bg-opacity-50 backdrop-blur-sm" aria-hidden="true"></div>

            <!-- Center modal -->
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <!-- Modal panel -->
            <div class="relative inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="px-6 pt-5 pb-4 bg-white sm:p-6 sm:pb-4">
                    <div class="text-center">
                        <h3 class="text-xl font-bold text-gray-900 mb-4" id="payment-modal-title">
                            <i class="fas fa-wallet text-indigo-600 mr-2"></i>
                            GCash Payment
                        </h3>

                        <!-- QR Code -->
                        <div class="mb-4">
                            <img src="../../assets/images/QR-Pay.jpg" alt="GCash QR Code"
                                class="mx-auto w-64 h-64 object-contain border-2 border-gray-200 rounded-lg">
                        </div>

                        <!-- Payment Summary -->
                        <div class="mb-4 p-4 bg-gray-50 rounded-lg space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Total Cost:</span>
                                <span class="font-semibold text-gray-900" id="modalTotalCost">₱0.00</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Already Paid:</span>
                                <span class="font-semibold text-green-600" id="modalPaidAmount">₱0.00</span>
                            </div>
                            <div class="flex justify-between text-sm border-t pt-2">
                                <span class="text-gray-600">Remaining:</span>
                                <span class="font-semibold text-indigo-600" id="modalRemainingAmount">₱0.00</span>
                            </div>
                        </div>

                        <!-- Payment Type Selection -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2 text-left">Payment Type</label>
                            <div class="grid grid-cols-3 gap-2">
                                <button type="button" onclick="selectPaymentType('full')" id="btn-full"
                                    class="payment-type-btn px-3 py-2 border-2 border-gray-300 rounded-lg text-sm font-medium hover:border-indigo-500 hover:bg-indigo-50 transition-colors">
                                    Full Payment
                                </button>
                                <button type="button" onclick="selectPaymentType('downpayment')" id="btn-downpayment"
                                    class="payment-type-btn px-3 py-2 border-2 border-gray-300 rounded-lg text-sm font-medium hover:border-indigo-500 hover:bg-indigo-50 transition-colors">
                                    Downpayment (30%)
                                </button>
                                <button type="button" onclick="selectPaymentType('partial')" id="btn-partial"
                                    class="payment-type-btn px-3 py-2 border-2 border-gray-300 rounded-lg text-sm font-medium hover:border-indigo-500 hover:bg-indigo-50 transition-colors">
                                    Partial
                                </button>
                            </div>
                        </div>

                        <!-- Payment Amount -->
                        <div class="mb-4">
                            <label for="paymentAmount" class="block text-sm font-medium text-gray-700 mb-2 text-left">
                                Amount to Pay <span class="text-red-500">*</span>
                            </label>
                            <input type="number" id="paymentAmount" step="0.01" min="0"
                                class="w-full px-4 py-3 text-center text-lg font-semibold border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <p class="text-xs text-gray-500 mt-1 text-left" id="amountHint">Select a payment type or enter custom amount</p>
                        </div>

                        <!-- Reference Number -->
                        <div class="mb-4">
                            <label for="gcashReference" class="block text-sm font-medium text-gray-700 mb-2 text-left">
                                GCash Reference Number <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="gcashReference" maxlength="13"
                                placeholder="Enter 13-digit reference number"
                                class="w-full px-4 py-3 text-center text-lg font-mono tracking-wider border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <p class="text-xs text-gray-500 mt-1 text-left">Example: 1234567890123</p>
                            <p id="paymentError" class="text-xs text-red-600 mt-1 hidden"></p>
                        </div>
                    </div>
                </div>
                <div class="px-4 py-3 bg-gray-50 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                    <button type="button" id="confirmPaymentBtn"
                        class="inline-flex justify-center w-full px-6 py-3 text-base font-medium text-white bg-indigo-600 border border-transparent rounded-lg shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:w-auto sm:text-sm transition-colors">
                        <i class="fas fa-check-circle mr-2"></i> Confirm Payment
                    </button>
                    <button type="button" id="cancelPaymentBtn"
                        class="inline-flex justify-center w-full px-6 py-3 mt-3 sm:mt-0 text-base font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:w-auto sm:text-sm transition-colors">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentEventId = null;
        let currentTotalCost = 0;
        let currentPaidAmount = 0;
        let currentRemainingAmount = 0;
        let selectedPaymentType = null;

        function openPaymentModal(eventId, totalCost, paidAmount) {
            currentEventId = eventId;
            currentTotalCost = parseFloat(totalCost);
            currentPaidAmount = parseFloat(paidAmount);
            currentRemainingAmount = currentTotalCost - currentPaidAmount;

            document.getElementById('modalTotalCost').textContent = '₱' + currentTotalCost.toFixed(2);
            document.getElementById('modalPaidAmount').textContent = '₱' + currentPaidAmount.toFixed(2);
            document.getElementById('modalRemainingAmount').textContent = '₱' + currentRemainingAmount.toFixed(2);

            document.getElementById('paymentAmount').value = '';
            document.getElementById('gcashReference').value = '';
            document.getElementById('paymentError').classList.add('hidden');

            // Reset payment type buttons
            document.querySelectorAll('.payment-type-btn').forEach(btn => {
                btn.classList.remove('border-indigo-500', 'bg-indigo-100', 'text-indigo-700');
                btn.classList.add('border-gray-300');
            });
            selectedPaymentType = null;

            document.body.style.overflow = 'hidden';
            document.getElementById('paymentModal').classList.remove('hidden');
        }

        function closePaymentModal() {
            document.body.style.overflow = '';
            document.getElementById('paymentModal').classList.add('hidden');
            currentEventId = null;
        }

        function selectPaymentType(type) {
            selectedPaymentType = type;

            // Update button styles
            document.querySelectorAll('.payment-type-btn').forEach(btn => {
                btn.classList.remove('border-indigo-500', 'bg-indigo-100', 'text-indigo-700');
                btn.classList.add('border-gray-300');
            });

            const selectedBtn = document.getElementById('btn-' + type);
            selectedBtn.classList.remove('border-gray-300');
            selectedBtn.classList.add('border-indigo-500', 'bg-indigo-100', 'text-indigo-700');

            // Set payment amount
            const paymentAmountInput = document.getElementById('paymentAmount');
            const amountHint = document.getElementById('amountHint');

            if (type === 'full') {
                paymentAmountInput.value = currentRemainingAmount.toFixed(2);
                amountHint.textContent = 'Full remaining balance';
            } else if (type === 'downpayment') {
                const downpayment = currentTotalCost * 0.3;
                paymentAmountInput.value = downpayment.toFixed(2);
                amountHint.textContent = 'Minimum 30% downpayment';
            } else if (type === 'partial') {
                paymentAmountInput.value = '';
                amountHint.textContent = 'Enter any amount up to remaining balance';
                paymentAmountInput.focus();
            }
        }

        // Validate reference number input
        document.getElementById('gcashReference').addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '');
        });

        // Cancel button
        document.getElementById('cancelPaymentBtn').addEventListener('click', closePaymentModal);
        document.getElementById('paymentModalBackdrop').addEventListener('click', closePaymentModal);

        // Confirm payment
        document.getElementById('confirmPaymentBtn').addEventListener('click', async function() {
            const amount = parseFloat(document.getElementById('paymentAmount').value);
            const reference = document.getElementById('gcashReference').value.trim();
            const errorEl = document.getElementById('paymentError');

            // Validation
            if (!selectedPaymentType) {
                errorEl.textContent = 'Please select a payment type';
                errorEl.classList.remove('hidden');
                return;
            }

            if (!amount || amount <= 0) {
                errorEl.textContent = 'Please enter a valid payment amount';
                errorEl.classList.remove('hidden');
                return;
            }

            if (amount > currentRemainingAmount) {
                errorEl.textContent = 'Amount cannot exceed remaining balance';
                errorEl.classList.remove('hidden');
                return;
            }

            if (selectedPaymentType === 'downpayment' && amount < (currentTotalCost * 0.3)) {
                errorEl.textContent = 'Downpayment must be at least 30% of total cost';
                errorEl.classList.remove('hidden');
                return;
            }

            if (!reference || reference.length !== 13) {
                errorEl.textContent = 'Please enter a valid 13-digit GCash reference number';
                errorEl.classList.remove('hidden');
                return;
            }

            // Disable button
            const confirmBtn = this;
            const originalText = confirmBtn.innerHTML;
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
            errorEl.classList.add('hidden');

            try {
                const formData = new FormData();
                formData.append('event_id', currentEventId);
                formData.append('payment_type', selectedPaymentType);
                formData.append('amount', amount);
                formData.append('reference_no', reference);

                const response = await fetch('../../../src/services/process-payment.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    closePaymentModal();
                    alert('Payment submitted successfully! Your payment is pending verification.');
                    location.reload();
                } else {
                    errorEl.textContent = data.error || 'Failed to process payment';
                    errorEl.classList.remove('hidden');
                }
            } catch (error) {
                console.error('Error:', error);
                errorEl.textContent = 'Connection error. Please try again.';
                errorEl.classList.remove('hidden');
            } finally {
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = originalText;
            }
        });
    </script>

</body>

</html>
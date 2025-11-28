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
        v.venue_name,
        v.location
    FROM events e
    LEFT JOIN venues v ON e.venue_id = v.venue_id
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

            <!-- Booking Cards -->
            <?php if ($result && $result->num_rows > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php while ($event = $result->fetch_assoc()): ?>
                <div
                    class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow">
                    <div class="p-6">
                        <div class="flex justify-between items-start mb-3">
                            <h3 class="text-xl font-bold text-gray-900">
                                <?php echo htmlspecialchars($event['event_name']); ?></h3>
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
                        </div>

                        <?php if (!empty($event['venue_name'])): ?>
                        <p class="text-sm text-gray-600 mb-2">
                            <i class="fas fa-building mr-2 text-indigo-600"></i>
                            <?php echo htmlspecialchars($event['venue_name']); ?>
                        </p>
                        <?php endif; ?>

                        <?php if (!empty($event['location'])): ?>
                        <p class="text-sm text-gray-600 mb-2">
                            <i class="fas fa-map-marker-alt mr-2 text-indigo-600"></i>
                            <?php echo htmlspecialchars($event['location']); ?>
                        </p>
                        <?php endif; ?>

                        <p class="text-sm text-gray-600 mb-2">
                            <i class="fas fa-calendar mr-2 text-indigo-600"></i>
                            <?php echo date('M d, Y \a\t g:i A', strtotime($event['event_date'])); ?>
                        </p>

                        <p class="text-lg font-bold text-indigo-600 mt-3">
                            ₱<?php echo number_format($event['total_cost'] ?? 0, 2); ?>
                        </p>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="text-center py-12">
                <div class="text-gray-400 mb-4">
                    <i class="fas fa-ticket-alt text-4xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No bookings yet</h3>
                <p class="text-gray-600">You haven't created any event bookings.</p>
                <a href="find-venues.php"
                    class="mt-4 inline-block px-4 py-2 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition-colors">
                    Find a Venue & Book
                </a>
            </div>
            <?php endif; ?>
            <?php if ($nav_layout === 'sidebar'): ?>
        </div>
        <?php endif; ?>
    </div>

</body>

</html>

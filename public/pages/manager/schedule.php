<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/opt/lampp/htdocs/Gatherly-EMS_2025/error.log');

// Log start
error_log("=== Schedule.php Debug Start ===");
error_log("Session User ID: " . ($_SESSION['user_id'] ?? 'NOT SET'));
error_log("Session Role: " . ($_SESSION['role'] ?? 'NOT SET'));

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    error_log("Access denied - redirecting to signin");
    header("Location: ../signin.php");
    exit();
}

try {
    require_once '../../../src/services/dbconnect.php';
    error_log("Database connection successful");
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection error. Check error log.");
}

$first_name = $_SESSION['first_name'] ?? 'Manager';
$manager_id = $_SESSION['user_id'];
$nav_layout = $_SESSION['nav_layout'] ?? 'navbar';

error_log("Manager ID: " . $manager_id);
error_log("First Name: " . $first_name);

// Fetch all events for manager's venues
$events_query = "SELECT 
    e.event_id,
    e.event_name,
    e.event_type,
    e.event_date,
    e.time_start,
    e.time_end,
    e.status,
    e.expected_guests,
    e.total_cost,
    e.theme,
    v.venue_name,
    v.venue_id,
    COALESCE(u.first_name, 'N/A') as organizer_first,
    COALESCE(u.last_name, '') as organizer_last,
    COALESCE(u.email, 'N/A') as organizer_email,
    COALESCE(u.phone, 'N/A') as organizer_phone
FROM events e
INNER JOIN venues v ON e.venue_id = v.venue_id
LEFT JOIN users u ON e.organizer_id = u.user_id
WHERE v.manager_id = ?
ORDER BY e.event_date DESC";

error_log("Events Query: " . $events_query);

try {
    $stmt = $conn->prepare($events_query);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        throw new Exception("Query preparation failed: " . $conn->error);
    }

    $stmt->bind_param("i", $manager_id);

    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        throw new Exception("Query execution failed: " . $stmt->error);
    }

    $events_result = $stmt->get_result();
    error_log("Events query executed successfully. Rows: " . $events_result->num_rows);

    $events = [];
    while ($row = $events_result->fetch_assoc()) {
        $events[] = $row;
    }
    error_log("Events fetched: " . count($events));
    $stmt->close();
} catch (Exception $e) {
    error_log("Events query error: " . $e->getMessage());
    die("Error loading events. Check error log for details.");
}

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_events,
    SUM(CASE WHEN e.status = 'pending' THEN 1 ELSE 0 END) as pending_events,
    SUM(CASE WHEN e.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_events,
    SUM(CASE WHEN e.status = 'completed' THEN 1 ELSE 0 END) as completed_events,
    SUM(CASE WHEN e.status = 'canceled' THEN 1 ELSE 0 END) as canceled_events
FROM events e
INNER JOIN venues v ON e.venue_id = v.venue_id
WHERE v.manager_id = ?";

error_log("Stats Query: " . $stats_query);

try {
    $stmt = $conn->prepare($stats_query);
    if (!$stmt) {
        error_log("Stats prepare failed: " . $conn->error);
        throw new Exception("Stats query preparation failed: " . $conn->error);
    }

    $stmt->bind_param("i", $manager_id);

    if (!$stmt->execute()) {
        error_log("Stats execute failed: " . $stmt->error);
        throw new Exception("Stats query execution failed: " . $stmt->error);
    }

    $stats_result = $stmt->get_result();
    $stats = $stats_result->fetch_assoc();
    error_log("Stats fetched: " . json_encode($stats));
    $stmt->close();
} catch (Exception $e) {
    error_log("Stats query error: " . $e->getMessage());
    die("Error loading statistics. Check error log for details.");
}

// Fetch manager's venues for filter
$venues_query = "SELECT venue_id, venue_name FROM venues WHERE manager_id = ? ORDER BY venue_name";
error_log("Venues Query: " . $venues_query);

try {
    $stmt = $conn->prepare($venues_query);
    if (!$stmt) {
        error_log("Venues prepare failed: " . $conn->error);
        throw new Exception("Venues query preparation failed: " . $conn->error);
    }

    $stmt->bind_param("i", $manager_id);

    if (!$stmt->execute()) {
        error_log("Venues execute failed: " . $stmt->error);
        throw new Exception("Venues query execution failed: " . $stmt->error);
    }

    $venues_result = $stmt->get_result();
    $venues = [];
    while ($row = $venues_result->fetch_assoc()) {
        $venues[] = $row;
    }
    error_log("Venues fetched: " . count($venues));
    $stmt->close();
} catch (Exception $e) {
    error_log("Venues query error: " . $e->getMessage());
    die("Error loading venues. Check error log for details.");
}

error_log("=== Schedule.php Debug End - All queries successful ===");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Schedule | Gatherly</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/logo.png">
    <link rel="stylesheet"
        href="../../../src/output.css?v=<?php echo filemtime(__DIR__ . '/../../../src/output.css'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"
        integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

    <!-- FullCalendar CSS -->
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css' rel='stylesheet' />

    <style>
        /* Calendar Container */
        .fc {
            font-family: 'Montserrat', sans-serif;
            font-size: 14px;
        }

        /* Calendar Events */
        .fc-event {
            cursor: pointer;
            border-radius: 6px;
            padding: 4px 6px;
            margin: 2px 0;
            font-size: 12px;
            font-weight: 500;
            border: none !important;
            transition: all 0.2s ease;
        }

        .fc-event:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .fc-daygrid-event {
            white-space: normal;
        }

        .fc-event-time {
            font-weight: 600;
            margin-right: 4px;
        }

        .fc-event-title {
            font-weight: 500;
        }

        /* Calendar Header */
        .fc-toolbar {
            padding: 16px;
            margin-bottom: 0 !important;
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border-radius: 12px 12px 0 0;
            border-bottom: 2px solid #16a34a;
        }

        .fc-toolbar-title {
            font-size: 1.75rem !important;
            font-weight: 700 !important;
            color: #166534;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        /* Calendar Buttons */
        .fc-button {
            text-transform: capitalize !important;
            font-weight: 600 !important;
            padding: 8px 16px !important;
            border-radius: 8px !important;
            transition: all 0.2s ease !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            margin: 0 4px !important;
        }

        .fc-button-primary {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%) !important;
            border: none !important;
        }

        .fc-button-primary:hover {
            background: linear-gradient(135deg, #15803d 0%, #166534 100%) !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(22, 163, 74, 0.3), 0 2px 4px -1px rgba(22, 163, 74, 0.2);
        }

        .fc-button-primary:not(:disabled).fc-button-active {
            background: linear-gradient(135deg, #166534 0%, #14532d 100%) !important;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .fc-button-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Calendar Grid */
        #calendar {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            overflow: hidden;
        }

        .fc-scrollgrid {
            border: none !important;
            border-radius: 0 0 12px 12px;
        }

        /* Day Headers */
        .fc-col-header-cell {
            background: linear-gradient(180deg, #f9fafb 0%, #f3f4f6 100%);
            border-color: #e5e7eb !important;
            padding: 12px 8px !important;
            font-weight: 700;
            color: #374151;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
        }

        /* Day Cells */
        .fc-daygrid-day {
            background: white;
            transition: background-color 0.2s ease;
        }

        .fc-daygrid-day:hover {
            background: #f9fafb;
        }

        .fc-daygrid-day-frame {
            min-height: 100px;
            padding: 4px;
        }

        .fc-daygrid-day-number {
            padding: 8px;
            font-weight: 600;
            color: #4b5563;
            font-size: 14px;
        }

        /* Today Highlight */
        .fc-day-today {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%) !important;
        }

        .fc-day-today .fc-daygrid-day-number {
            background: #16a34a;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 4px;
        }

        /* Weekend Days */
        .fc-day-sat,
        .fc-day-sun {
            background: #fafafa;
        }

        /* Other Month Days */
        .fc-day-other .fc-daygrid-day-number {
            color: #d1d5db;
        }

        /* Border Styling */
        .fc-theme-standard td,
        .fc-theme-standard th {
            border-color: #e5e7eb !important;
        }

        /* More Link */
        .fc-daygrid-more-link {
            color: #16a34a;
            font-weight: 600;
            font-size: 11px;
            background: #f0fdf4;
            padding: 2px 6px;
            border-radius: 4px;
            margin-top: 2px;
        }

        .fc-daygrid-more-link:hover {
            background: #dcfce7;
            color: #15803d;
        }

        /* Popover */
        .fc-popover {
            border-radius: 8px !important;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb !important;
        }

        .fc-popover-header {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            color: #166534;
            font-weight: 600;
            padding: 12px !important;
            border-radius: 8px 8px 0 0 !important;
        }

        /* List View Styling */
        .fc-list-day-cushion {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            color: #166534;
            font-weight: 700;
        }

        .fc-list-event:hover td {
            background: #f9fafb;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .fc-toolbar {
                flex-direction: column;
                gap: 12px;
            }

            .fc-toolbar-title {
                font-size: 1.25rem !important;
            }

            .fc-button {
                padding: 6px 12px !important;
                font-size: 12px !important;
            }

            .fc-daygrid-day-frame {
                min-height: 80px;
            }
        }
    </style>
</head>

<body class="bg-gradient-to-br from-green-50 via-white to-teal-50 font-['Montserrat']">

    <?php include '../../../src/components/ManagerSidebar.php'; ?>

    <div class="<?php echo $nav_layout === 'sidebar' ? 'md:ml-64' : ''; ?> transition-all duration-300">
        <?php if ($nav_layout === 'sidebar'): ?>
            <div class="min-h-screen">
            <?php endif; ?>

            <!-- Top Bar -->
            <div class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-20 px-4 sm:px-6 lg:px-8 py-4 mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Event Schedule</h1>
                        <p class="text-sm text-gray-600">View and manage your venue bookings</p>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="hidden sm:flex items-center gap-2 text-sm">
                            <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full font-medium">
                                <i class="fas fa-clock mr-1"></i><?php echo $stats['pending_events']; ?> Pending
                            </span>
                            <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full font-medium">
                                <i class="fas fa-check mr-1"></i><?php echo $stats['confirmed_events']; ?> Confirmed
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="px-4 sm:px-6 lg:px-8 pb-8">
                <!-- Stats Cards -->
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs text-gray-600 mb-1">Total Events</p>
                                <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total_events']; ?></p>
                            </div>
                            <div class="bg-blue-100 rounded-full p-3">
                                <i class="fas fa-calendar-alt text-blue-600"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs text-gray-600 mb-1">Pending</p>
                                <p class="text-2xl font-bold text-yellow-600"><?php echo $stats['pending_events']; ?>
                                </p>
                            </div>
                            <div class="bg-yellow-100 rounded-full p-3">
                                <i class="fas fa-clock text-yellow-600"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs text-gray-600 mb-1">Confirmed</p>
                                <p class="text-2xl font-bold text-green-600"><?php echo $stats['confirmed_events']; ?>
                                </p>
                            </div>
                            <div class="bg-green-100 rounded-full p-3">
                                <i class="fas fa-check-circle text-green-600"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs text-gray-600 mb-1">Completed</p>
                                <p class="text-2xl font-bold text-blue-600"><?php echo $stats['completed_events']; ?>
                                </p>
                            </div>
                            <div class="bg-blue-100 rounded-full p-3">
                                <i class="fas fa-check-double text-blue-600"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs text-gray-600 mb-1">Canceled</p>
                                <p class="text-2xl font-bold text-red-600"><?php echo $stats['canceled_events']; ?></p>
                            </div>
                            <div class="bg-red-100 rounded-full p-3">
                                <i class="fas fa-times-circle text-red-600"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-filter text-gray-600"></i>
                            <span class="font-semibold text-gray-700">Filters:</span>
                        </div>

                        <select id="venueFilter"
                            class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="">All Venues</option>
                            <?php foreach ($venues as $venue): ?>
                                <option value="<?php echo $venue['venue_id']; ?>">
                                    <?php echo htmlspecialchars($venue['venue_name']); ?></option>
                            <?php endforeach; ?>
                        </select>

                        <select id="statusFilter"
                            class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="completed">Completed</option>
                            <option value="canceled">Canceled</option>
                        </select>

                        <button id="clearFilters"
                            class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors">
                            <i class="fas fa-redo mr-2"></i>Clear Filters
                        </button>

                        <div class="ml-auto flex items-center gap-2">
                            <button id="viewToggle"
                                class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors">
                                <i class="fas fa-list mr-2"></i>List View
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Calendar -->
                <div id="calendarView">
                    <div id="calendar"></div>
                </div>

                <!-- List View (Hidden by default) -->
                <div id="listView" class="hidden">
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50 border-b border-gray-200">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">
                                            Event</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">
                                            Venue</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">
                                            Date & Time</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">
                                            Organizer</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">
                                            Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">
                                            Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="eventListBody" class="divide-y divide-gray-200">
                                    <!-- Will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($nav_layout === 'sidebar'): ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Event Detail Modal -->
    <div id="eventModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                <h3 class="text-xl font-bold text-gray-800">Event Details</h3>
                <button onclick="closeEventModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div id="eventModalContent" class="p-6">
                <!-- Will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <!-- FullCalendar JS -->
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>

    <script>
        // PHP events data to JavaScript
        const eventsData = <?php echo json_encode($events); ?>;
        let calendar;
        let currentView = 'calendar';
        let currentFilters = {
            venue: '',
            status: ''
        };

        // Format events for FullCalendar
        function formatEventsForCalendar(events) {
            return events.map(event => {
                let color;
                switch (event.status) {
                    case 'pending':
                        color = '#EAB308';
                        break;
                    case 'confirmed':
                        color = '#22C55E';
                        break;
                    case 'completed':
                        color = '#3B82F6';
                        break;
                    case 'canceled':
                        color = '#EF4444';
                        break;
                    default:
                        color = '#6B7280';
                }

                return {
                    id: event.event_id,
                    title: event.event_name,
                    start: event.event_date + 'T' + event.time_start,
                    end: event.event_date + 'T' + event.time_end,
                    backgroundColor: color,
                    borderColor: color,
                    extendedProps: {
                        ...event
                    }
                };
            });
        }

        // Filter events
        function getFilteredEvents() {
            let filtered = eventsData;

            if (currentFilters.venue) {
                filtered = filtered.filter(e => e.venue_id == currentFilters.venue);
            }

            if (currentFilters.status) {
                filtered = filtered.filter(e => e.status === currentFilters.status);
            }

            return filtered;
        }

        // Initialize FullCalendar
        document.addEventListener('DOMContentLoaded', function() {
            const calendarEl = document.getElementById('calendar');

            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
                },
                events: formatEventsForCalendar(eventsData),
                eventClick: function(info) {
                    showEventDetails(info.event.extendedProps);
                },
                eventMouseEnter: function(info) {
                    info.el.style.cursor = 'pointer';
                },
                height: 'auto',
                eventTimeFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    meridiem: 'short'
                }
            });

            calendar.render();

            // Filter event listeners
            document.getElementById('venueFilter').addEventListener('change', function() {
                currentFilters.venue = this.value;
                updateCalendar();
            });

            document.getElementById('statusFilter').addEventListener('change', function() {
                currentFilters.status = this.value;
                updateCalendar();
            });

            document.getElementById('clearFilters').addEventListener('click', function() {
                currentFilters = {
                    venue: '',
                    status: ''
                };
                document.getElementById('venueFilter').value = '';
                document.getElementById('statusFilter').value = '';
                updateCalendar();
            });

            // View toggle
            document.getElementById('viewToggle').addEventListener('click', toggleView);
        });

        function updateCalendar() {
            const filtered = getFilteredEvents();
            calendar.removeAllEvents();
            calendar.addEventSource(formatEventsForCalendar(filtered));
            updateListView(filtered);
        }

        function toggleView() {
            const calendarView = document.getElementById('calendarView');
            const listView = document.getElementById('listView');
            const toggleBtn = document.getElementById('viewToggle');

            if (currentView === 'calendar') {
                calendarView.classList.add('hidden');
                listView.classList.remove('hidden');
                toggleBtn.innerHTML = '<i class="fas fa-calendar mr-2"></i>Calendar View';
                currentView = 'list';
                updateListView(getFilteredEvents());
            } else {
                calendarView.classList.remove('hidden');
                listView.classList.add('hidden');
                toggleBtn.innerHTML = '<i class="fas fa-list mr-2"></i>List View';
                currentView = 'calendar';
            }
        }

        function updateListView(events) {
            const tbody = document.getElementById('eventListBody');
            tbody.innerHTML = '';

            if (events.length === 0) {
                tbody.innerHTML =
                    '<tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">No events found</td></tr>';
                return;
            }

            events.forEach(event => {
                const statusColors = {
                    'pending': 'bg-yellow-100 text-yellow-800',
                    'confirmed': 'bg-green-100 text-green-800',
                    'completed': 'bg-blue-100 text-blue-800',
                    'canceled': 'bg-red-100 text-red-800'
                };

                const row = document.createElement('tr');
                row.className = 'hover:bg-gray-50';
                row.innerHTML = `
                    <td class="px-6 py-4">
                        <p class="font-semibold text-gray-800">${escapeHtml(event.event_name)}</p>
                        <p class="text-xs text-gray-500">${escapeHtml(event.event_type)}</p>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-700">${escapeHtml(event.venue_name)}</td>
                    <td class="px-6 py-4">
                        <p class="text-sm text-gray-700">${formatDate(event.event_date)}</p>
                        <p class="text-xs text-gray-500">${formatTime(event.time_start)} - ${formatTime(event.time_end)}</p>
                    </td>
                    <td class="px-6 py-4">
                        <p class="text-sm text-gray-700">${escapeHtml(event.organizer_first + ' ' + event.organizer_last)}</p>
                        <p class="text-xs text-gray-500">${escapeHtml(event.organizer_phone || '')}</p>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs font-semibold rounded-full ${statusColors[event.status] || 'bg-gray-100 text-gray-800'}">
                            ${event.status.charAt(0).toUpperCase() + event.status.slice(1)}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <button onclick='showEventDetails(${JSON.stringify(event)})' class="text-green-600 hover:text-green-700">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        function showEventDetails(event) {
            const modal = document.getElementById('eventModal');
            const content = document.getElementById('eventModalContent');

            const statusColors = {
                'pending': 'bg-yellow-100 text-yellow-800',
                'confirmed': 'bg-green-100 text-green-800',
                'completed': 'bg-blue-100 text-blue-800',
                'canceled': 'bg-red-100 text-red-800'
            };

            content.innerHTML = `
                <div class="space-y-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <h4 class="text-2xl font-bold text-gray-800 mb-2">${escapeHtml(event.event_name)}</h4>
                            <span class="px-3 py-1 text-sm font-semibold rounded-full ${statusColors[event.status]}">
                                ${event.status.charAt(0).toUpperCase() + event.status.slice(1)}
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <div>
                                <p class="text-sm font-semibold text-gray-600 mb-1">
                                    <i class="fas fa-building text-green-600 mr-2"></i>Venue
                                </p>
                                <p class="text-gray-800">${escapeHtml(event.venue_name)}</p>
                            </div>

                            <div>
                                <p class="text-sm font-semibold text-gray-600 mb-1">
                                    <i class="fas fa-calendar text-green-600 mr-2"></i>Event Date
                                </p>
                                <p class="text-gray-800">${formatDate(event.event_date)}</p>
                            </div>

                            <div>
                                <p class="text-sm font-semibold text-gray-600 mb-1">
                                    <i class="fas fa-clock text-green-600 mr-2"></i>Time
                                </p>
                                <p class="text-gray-800">${formatTime(event.time_start)} - ${formatTime(event.time_end)}</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <p class="text-sm font-semibold text-gray-600 mb-1">
                                    <i class="fas fa-tag text-green-600 mr-2"></i>Event Type
                                </p>
                                <p class="text-gray-800">${escapeHtml(event.event_type)}</p>
                            </div>

                            <div>
                                <p class="text-sm font-semibold text-gray-600 mb-1">
                                    <i class="fas fa-users text-green-600 mr-2"></i>Expected Guests
                                </p>
                                <p class="text-gray-800">${event.expected_guests} guests</p>
                            </div>

                            <div>
                                <p class="text-sm font-semibold text-gray-600 mb-1">
                                    <i class="fas fa-peso-sign text-green-600 mr-2"></i>Total Cost
                                </p>
                                <p class="text-gray-800">₱${parseFloat(event.total_cost).toLocaleString('en-PH', {minimumFractionDigits: 2})}</p>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-4">
                        <p class="text-sm font-semibold text-gray-600 mb-3">
                            <i class="fas fa-user text-green-600 mr-2"></i>Organizer Information
                        </p>
                        <div class="bg-gray-50 rounded-lg p-4 space-y-2">
                            <p class="text-gray-800"><strong>Name:</strong> ${escapeHtml(event.organizer_first + ' ' + event.organizer_last)}</p>
                            <p class="text-gray-800"><strong>Email:</strong> ${escapeHtml(event.organizer_email || 'N/A')}</p>
                            <p class="text-gray-800"><strong>Phone:</strong> ${escapeHtml(event.organizer_phone || 'N/A')}</p>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                        <button onclick="closeEventModal()" class="px-6 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition-colors">
                            Close
                        </button>
                        <a href="bookings.php" class="px-6 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors">
                            View All Bookings
                        </a>
                    </div>
                </div>
            `;

            modal.classList.remove('hidden');
        }

        function closeEventModal() {
            document.getElementById('eventModal').classList.add('hidden');
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }

        function formatTime(timeString) {
            const [hours, minutes] = timeString.split(':');
            const hour = parseInt(hours);
            const ampm = hour >= 12 ? 'PM' : 'AM';
            const hour12 = hour % 12 || 12;
            return `${hour12}:${minutes} ${ampm}`;
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Close modal when clicking outside
        document.getElementById('eventModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEventModal();
            }
        });
    </script>

</body>

</html>
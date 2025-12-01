<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gatherly | An Event Management Platform</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/logo.png">
    <link rel="stylesheet" href="../../src/output.css?v=2">
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

<body>
    <div class="min-h-screen font-['Montserrat']">
        <!-- Navbar -->
        <nav class="sticky top-0 z-50 w-full border-b border-gray-200 shadow-md bg-white/90 backdrop-blur-lg">
            <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-12 md:h-16">
                    <div class="flex items-center h-full">
                        <a href="index.php" class="flex items-center group">
                            <img class="w-8 h-8 mr-2 transition-transform sm:w-10 sm:h-10 group-hover:scale-110"
                                src="public/assets/images/logo.png" alt="Gatherly Logo">
                            <span class="text-lg font-bold text-gray-800 sm:text-xl">Gatherly</span>
                        </a>
                    </div>
                    <div class="hidden md:block">
                        <div class="flex items-center space-x-1 lg:space-x-2">
                            <a href="#home"
                                class="px-3 py-2 text-sm font-semibold text-gray-700 transition-all duration-200 rounded-lg lg:text-base hover:bg-indigo-50 hover:text-indigo-600">Home</a>
                            <a href="#features"
                                class="px-3 py-2 text-sm font-semibold text-gray-700 transition-all duration-200 rounded-lg lg:text-base hover:bg-indigo-50 hover:text-indigo-600">Features</a>
                            <a href="#how-it-works"
                                class="px-3 py-2 text-sm font-semibold text-gray-700 transition-all duration-200 rounded-lg lg:text-base hover:bg-indigo-50 hover:text-indigo-600">Guide</a>
                            <a href="#smart-features"
                                class="px-3 py-2 text-sm font-semibold text-gray-700 transition-all duration-200 rounded-lg lg:text-base hover:bg-indigo-50 hover:text-indigo-600">Overview</a>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="public/pages/signin.php"
                            class="px-3 sm:px-4 py-2 text-sm sm:text-base font-semibold text-white transition-all bg-indigo-600 rounded-lg shadow-md hover:bg-indigo-700 hover:shadow-lg hover:-translate-y-0.5">Sign
                            in</a>
                        <button id="mobile-menu-button"
                            class="p-2 text-gray-700 rounded-lg md:hidden hover:bg-gray-100">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 6h16M4 12h16M4 18h16"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                <!-- Mobile Menu -->
                <div id="mobile-menu" class="hidden pb-4 space-y-2 md:hidden">
                    <a href="#home"
                        class="block px-3 py-2 text-sm font-semibold text-gray-700 rounded-lg hover:bg-indigo-50 hover:text-indigo-600">Home</a>
                    <a href="#features"
                        class="block px-3 py-2 text-sm font-semibold text-gray-700 rounded-lg hover:bg-indigo-50 hover:text-indigo-600">Features</a>
                    <a href="#how-it-works"
                        class="block px-3 py-2 text-sm font-semibold text-gray-700 rounded-lg hover:bg-indigo-50 hover:text-indigo-600">How
                        it works</a>
                    <a href="#smart-features"
                        class="block px-3 py-2 text-sm font-semibold text-gray-700 rounded-lg hover:bg-indigo-50 hover:text-indigo-600">Smart
                        features</a>
                </div>
            </div>
        </nav>
        <div class="grid grid-rows-[auto_1fr_auto]">
            <!-- Hero Section -->
            <div id="home"
                class="relative pt-8 overflow-hidden bg-center bg-no-repeat bg-cover sm:pt-12 md:pt-16 hero-section"
                style="background-image: url('public/assets/images/hero-bg.png'); will-change: background-position; min-height: 500px; height: calc(100vh - 4rem);"
                data-pan-speed="0.45">
                <div class="absolute inset-0 bg-linear-to-br from-indigo-900/30 via-purple-900/10 to-transparent">
                </div>
                <div class="flex flex-col justify-center h-full px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div class="relative z-10 pb-8 sm:pb-16 md:pb-20 lg:max-w-5xl lg:w-full lg:pb-28 xl:pb-32">
                        <main class="mt-6 sm:mt-10 md:mt-16 lg:mt-20 xl:mt-28">
                            <div class="text-center lg:text-left">
                                <h1
                                    class="text-3xl font-extrabold leading-tight text-white sm:text-4xl md:text-5xl lg:text-7xl">
                                    <span class="block">Organize Events</span>
                                    <span class="block text-indigo-300">Seamlessly</span>
                                </h1>
                                <p
                                    class="max-w-2xl mx-auto mt-4 text-sm leading-relaxed text-gray-100 sm:mt-5 md:mt-6 sm:text-base md:text-lg lg:text-xl lg:mx-0">
                                    Gatherly simplifies event management with powerful tools for planning, promotion,
                                    and attendee engagement.
                                </p>
                                <div
                                    class="flex flex-col items-center justify-center gap-3 mt-6 sm:mt-8 sm:flex-row sm:gap-4 lg:justify-start">
                                    <a href="public/pages/signup.php"
                                        class="w-full px-6 py-3 text-sm font-semibold text-white transition-all bg-indigo-600 shadow-xl sm:w-auto sm:px-8 sm:py-4 sm:text-base lg:text-lg rounded-xl hover:bg-indigo-700 hover:shadow-2xl hover:-translate-y-1">
                                        Get Started Free
                                    </a>
                                    <a href="#features"
                                        class="w-full px-6 py-3 text-sm font-semibold text-white transition-all border-2 sm:w-auto sm:px-8 sm:py-4 sm:text-base lg:text-lg bg-white/10 backdrop-blur-sm border-white/30 rounded-xl hover:bg-white/20">
                                        Learn More
                                    </a>
                                </div>
                            </div>
                        </main>
                    </div>
                </div>
            </div>
            <!-- Features -->
            <div id="features" class="py-12 sm:py-16 md:py-20 bg-gray-50">
                <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div class="flex flex-col items-center mb-8 text-center sm:mb-12">
                        <h2 class="text-2xl font-bold text-gray-900 sm:text-3xl md:text-4xl">Why Choose Gatherly?</h2>
                        <p class="mt-2 text-base text-gray-600 sm:mt-3 sm:text-lg md:text-xl">The smartest way to manage
                            your events</p>
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 sm:gap-6">
                        <div
                            class="flex flex-col items-center justify-start gap-3 p-6 transition-all bg-white shadow-lg sm:p-8 rounded-xl hover:shadow-xl hover:-translate-y-1 group">
                            <div class="p-4 transition-colors bg-indigo-100 rounded-xl group-hover:bg-indigo-200">

                                <i class="text-2xl text-indigo-600 sm:text-3xl fa-solid fa-comment-nodes"
                                    aria-hidden="true"></i>
                            </div>
                            <h3 class="mt-2 text-base font-bold text-gray-800 sm:text-lg">AI-Powered Matching</h3>
                            <p class="text-xs leading-relaxed text-center text-gray-600 sm:text-sm">Smart
                                recommendations based on your event requirements and preferences</p>
                        </div>
                        <div
                            class="flex flex-col items-center justify-start gap-3 p-6 transition-all bg-white shadow-lg sm:p-8 rounded-xl hover:shadow-xl hover:-translate-y-1 group">
                            <div class="p-4 transition-colors bg-indigo-100 rounded-xl group-hover:bg-indigo-200">
                                <i class="text-2xl text-indigo-600 sm:text-3xl fa-solid fa-arrow-trend-up"
                                    aria-hidden="true"></i>
                            </div>
                            <h3 class="mt-2 text-base font-bold text-gray-800 sm:text-lg">Dynamic Pricing</h3>
                            <p class="text-xs leading-relaxed text-center text-gray-600 sm:text-sm">Get the best rates
                                with real-time pricing based on demand and seasonality</p>
                        </div>
                        <div
                            class="flex flex-col items-center justify-start gap-3 p-6 transition-all bg-white shadow-lg sm:p-8 rounded-xl hover:shadow-xl hover:-translate-y-1 group">
                            <div class="p-4 transition-colors bg-indigo-100 rounded-xl group-hover:bg-indigo-200">
                                <i class="text-2xl text-indigo-600 sm:text-3xl fa-solid fa-shield-halved"
                                    aria-hidden="true"></i>
                            </div>
                            <h3 class="mt-2 text-base font-bold text-gray-800 sm:text-lg">Secure Contracts</h3>
                            <p class="text-xs leading-relaxed text-center text-gray-600 sm:text-sm">Auto-generated
                                agreements with transparent terms and conditions</p>
                        </div>
                        <div
                            class="flex flex-col items-center justify-start gap-3 p-6 transition-all bg-white shadow-lg sm:p-8 rounded-xl hover:shadow-xl hover:-translate-y-1 group">
                            <div class="p-4 transition-colors bg-indigo-100 rounded-xl group-hover:bg-indigo-200">
                                <i class="text-2xl text-indigo-600 sm:text-3xl fa-solid fa-bolt" aria-hidden="true"></i>
                            </div>
                            <h3 class="mt-2 text-base font-bold text-gray-800 sm:text-lg">Lightning Fast Setup</h3>
                            <p class="text-xs leading-relaxed text-center text-gray-600 sm:text-sm">Get your event up
                                and running in no time with our streamlined process</p>
                        </div>
                    </div>
                </div>
            </div>
            <!-- How it Works -->
            <section id="how-it-works" class="py-12 bg-white sm:py-16 md:py-20">
                <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div class="flex flex-col items-center mb-8 text-center sm:mb-12">
                        <h2 class="text-2xl font-bold text-gray-900 sm:text-3xl md:text-4xl">How Gatherly Works</h2>
                        <p class="mt-2 text-base text-gray-600 sm:mt-3 sm:text-lg md:text-xl">A simple, guided flow for
                            organizers and venue managers</p>
                    </div>
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 sm:gap-8 lg:items-stretch">
                        <!-- Organizer Flow -->
                        <div
                            class="flex flex-col p-6 transition-all border border-gray-200 shadow-lg rounded-2xl bg-linear-to-br from-indigo-50 to-white sm:p-8 hover:shadow-xl hover:-translate-y-1">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="p-3 text-white bg-indigo-600 shadow-md rounded-xl">
                                    <i class="text-xl fa-solid fa-people-group" aria-hidden="true"></i>
                                </div>
                                <h3 class="text-lg font-bold text-gray-900 sm:text-xl">For Organizers</h3>
                            </div>
                            <ol class="space-y-6">
                                <li class="flex items-start gap-4 group min-h-12">
                                    <span
                                        class="flex items-center justify-center w-8 h-8 text-sm font-bold text-white transition-transform bg-indigo-600 rounded-full shadow-md shrink-0 min-w-8 group-hover:scale-110">1</span>
                                    <p class="flex-1 text-sm leading-relaxed text-gray-700 sm:text-base">Search venues
                                        and get <span class="font-semibold text-indigo-700">smart recommendations</span>
                                        based on capacity, budget, and amenities.</p>
                                </li>
                                <li class="flex items-start gap-4 group min-h-12">
                                    <span
                                        class="flex items-center justify-center w-8 h-8 text-sm font-bold text-white transition-transform bg-indigo-600 rounded-full shadow-md shrink-0 min-w-8 group-hover:scale-110">2</span>
                                    <p class="flex-1 text-sm leading-relaxed text-gray-700 sm:text-base">Check <span
                                            class="font-semibold text-indigo-700">real-time availability</span> and
                                        dynamic pricing suggestions.</p>
                                </li>
                                <li class="flex items-start gap-4 group min-h-12">
                                    <span
                                        class="flex items-center justify-center w-8 h-8 text-sm font-bold text-white transition-transform bg-indigo-600 rounded-full shadow-md shrink-0 min-w-8 group-hover:scale-110">3</span>
                                    <p class="flex-1 text-sm leading-relaxed text-gray-700 sm:text-base">Reserve
                                        securely, then receive an <span
                                            class="font-semibold text-indigo-700">auto-generated contract</span>.</p>
                                </li>
                                <li class="flex items-start gap-4 group min-h-12">
                                    <span
                                        class="flex items-center justify-center w-8 h-8 text-sm font-bold text-white transition-transform bg-indigo-600 rounded-full shadow-md shrink-0 min-w-8 group-hover:scale-110">4</span>
                                    <p class="flex-1 text-sm leading-relaxed text-gray-700 sm:text-base">Chat with the
                                        venue manager and track updates in your dashboard.</p>
                                </li>
                            </ol>
                        </div>

                        <!-- Venue Manager Flow -->
                        <div
                            class="flex flex-col p-6 transition-all border border-gray-200 shadow-lg rounded-2xl bg-linear-to-br from-sky-50 to-white sm:p-8 hover:shadow-xl hover:-translate-y-1">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="p-3 text-white shadow-md rounded-xl bg-sky-600">
                                    <i class="text-xl fa-solid fa-building" aria-hidden="true"></i>
                                </div>
                                <h3 class="text-lg font-bold text-gray-900 sm:text-xl">For Venue Managers</h3>
                            </div>
                            <ol class="space-y-6">
                                <li class="flex items-start gap-4 group min-h-12">
                                    <span
                                        class="flex items-center justify-center w-8 h-8 text-sm font-bold text-white transition-transform rounded-full shadow-md shrink-0 min-w-8 bg-sky-600 group-hover:scale-110">1</span>
                                    <p class="flex-1 text-sm leading-relaxed text-gray-700 sm:text-base">Manage venue
                                        profiles, amenities, pricing, and calendars in one place.</p>
                                </li>
                                <li class="flex items-start gap-4 group min-h-12">
                                    <span
                                        class="flex items-center justify-center w-8 h-8 text-sm font-bold text-white transition-transform rounded-full shadow-md shrink-0 min-w-8 bg-sky-600 group-hover:scale-110">2</span>
                                    <p class="flex-1 text-sm leading-relaxed text-gray-700 sm:text-base">Avoid conflicts
                                        with <span class="font-semibold text-sky-700">instant availability
                                            updates</span> and conflict prevention.</p>
                                </li>
                                <li class="flex items-start gap-4 group min-h-12">
                                    <span
                                        class="flex items-center justify-center w-8 h-8 text-sm font-bold text-white transition-transform rounded-full shadow-md shrink-0 min-w-8 bg-sky-600 group-hover:scale-110">3</span>
                                    <p class="flex-1 text-sm leading-relaxed text-gray-700 sm:text-base">Leverage <span
                                            class="font-semibold text-sky-700">dynamic pricing</span> and forecasting to
                                        maximize occupancy.</p>
                                </li>
                                <li class="flex items-start gap-4 group min-h-12">
                                    <span
                                        class="flex items-center justify-center w-8 h-8 text-sm font-bold text-white transition-transform rounded-full shadow-md shrink-0 min-w-8 bg-sky-600 group-hover:scale-110">4</span>
                                    <p class="flex-1 text-sm leading-relaxed text-gray-700 sm:text-base">Handle
                                        inquiries via <span class="font-semibold text-sky-700">in-app chat</span> and
                                        close with digital contracts.</p>
                                </li>
                            </ol>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Smart Features Deep Dive -->
            <section id="smart-features" class="py-12 sm:py-16 md:py-20 bg-gray-50">
                <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div class="flex flex-col items-center mb-8 text-center sm:mb-12">
                        <h2 class="text-2xl font-bold text-gray-900 sm:text-3xl md:text-4xl">Overview
                        </h2>
                        <p class="mt-2 text-base text-gray-600 sm:mt-3 sm:text-lg md:text-xl">What makes Gatherly
                            intelligent and effective</p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 sm:gap-6">
                        <div
                            class="p-6 transition-all bg-white shadow-lg rounded-xl hover:shadow-xl hover:-translate-y-1">
                            <div class="flex items-center gap-3">
                                <div class="p-3 text-indigo-700 bg-indigo-100 rounded-lg">
                                    <i class="text-lg fa-solid fa-sliders" aria-hidden="true"></i>
                                </div>
                                <h3 class="font-semibold text-gray-800">MCDM Recommendations</h3>
                            </div>
                            <p class="mt-3 text-sm text-gray-600">Multi-criteria decision-making balances capacity,
                                budget, location, amenities, accessibility, and more to rank venues.</p>
                        </div>

                        <div
                            class="p-6 transition-all bg-white shadow-lg rounded-xl hover:shadow-xl hover:-translate-y-1">
                            <div class="flex items-center gap-3">
                                <div class="p-3 text-purple-700 bg-purple-100 rounded-lg">
                                    <i class="text-lg fa-solid fa-user-group" aria-hidden="true"></i>
                                </div>
                                <h3 class="font-semibold text-gray-800">Collaborative Filtering</h3>
                            </div>
                            <p class="mt-3 text-sm text-gray-600">Learns from similar organizers and past selections to
                                surface relevant venues faster.</p>
                        </div>

                        <div
                            class="p-6 transition-all bg-white shadow-lg rounded-xl hover:shadow-xl hover:-translate-y-1">
                            <div class="flex items-center gap-3">
                                <div class="p-3 rounded-lg bg-amber-100 text-amber-700">
                                    <i class="text-lg fa-solid fa-chart-line" aria-hidden="true"></i>
                                </div>
                                <h3 class="font-semibold text-gray-800">Dynamic Pricing & Forecasting</h3>
                            </div>
                            <p class="mt-3 text-sm text-gray-600">Season, day-of-week, and demand signals adjust
                                pricing. Forecasting predicts occupancy and optimal rates.</p>
                        </div>

                        <div
                            class="p-6 transition-all bg-white shadow-lg rounded-xl hover:shadow-xl hover:-translate-y-1">
                            <div class="flex items-center gap-3">
                                <div class="p-3 rounded-lg bg-rose-100 text-rose-700">
                                    <i class="text-lg fa-solid fa-calendar-check" aria-hidden="true"></i>
                                </div>
                                <h3 class="font-semibold text-gray-800">Conflict Prevention</h3>
                            </div>
                            <p class="mt-3 text-sm text-gray-600">Real-time calendar updates prevent double-bookings and
                                suggest viable alternatives instantly.</p>
                        </div>

                        <div
                            class="p-6 transition-all bg-white shadow-lg rounded-xl hover:shadow-xl hover:-translate-y-1">
                            <div class="flex items-center gap-3">
                                <div class="p-3 rounded-lg bg-emerald-100 text-emerald-700">
                                    <i class="text-lg fa-solid fa-chart-pie" aria-hidden="true"></i>
                                </div>
                                <h3 class="font-semibold text-gray-800">Analytics Dashboard</h3>
                            </div>
                            <p class="mt-3 text-sm text-gray-600">Track occupancy, revenue trends, and event popularity
                                with actionable insights.</p>
                        </div>

                        <div
                            class="p-6 transition-all bg-white shadow-lg rounded-xl hover:shadow-xl hover:-translate-y-1">
                            <div class="flex items-center gap-3">
                                <div class="p-3 rounded-lg bg-sky-100 text-sky-700">
                                    <i class="text-lg fa-solid fa-file-signature" aria-hidden="true"></i>
                                </div>
                                <h3 class="font-semibold text-gray-800">Chat & Contracts</h3>
                            </div>
                            <p class="mt-3 text-sm text-gray-600">In-app messaging accelerates coordination. Digital
                                contracts ensure secure, transparent agreements.</p>
                        </div>
                    </div>
                </div>
            </section>
            <!-- Call to Action -->
            <div class="relative py-12 overflow-hidden sm:py-16 md:py-20 lg:py-24">
                <div class="absolute inset-0 overflow-hidden">
                    <!-- Soft colorful blobs -->
                    <div
                        class="absolute w-96 h-96 rounded-full filter blur-[72px] opacity-60 pointer-events-none left-10 sm:left-80 top-20 sm:top-40 bg-[radial-gradient(circle_at_30%_30%,#7c3aed_0%,#5b21b6_40%,transparent_60%)] animate-[bounce_8s_ease-in-out_infinite]">
                    </div>
                    <div
                        class="absolute w-96 h-96 rounded-full filter blur-[72px] opacity-60 pointer-events-none right-10 sm:right-72 bottom-20 sm:bottom-48 bg-[radial-gradient(circle_at_70%_70%,#06b6d4_0%,#0891b2_40%,transparent_60%)] animate-[bounce_10s_ease-in-out_infinite]">
                    </div>

                    <!-- Subtle diagonal SVG pattern -->
                    <svg class="absolute inset-0 w-full h-full" preserveAspectRatio="none"
                        xmlns="http://www.w3.org/2000/svg" fill="none" aria-hidden="true">
                        <defs>
                            <pattern id="diagonal" width="40" height="40" patternUnits="userSpaceOnUse"
                                patternTransform="rotate(30)">
                                <path d="M0 0 L0 40" stroke="rgba(255,255,255,0.03)" stroke-width="2" />
                            </pattern>
                        </defs>
                        <rect width="100%" height="100%" fill="url(#diagonal)"></rect>
                    </svg>

                    <!-- Color overlay for depth -->
                    <div
                        class="absolute inset-0 bg-linear-to-r from-indigo-800 via-indigo-600 to-pink-600 opacity-60 mix-blend-multiply">
                    </div>
                </div>

                <div class="relative z-10 max-w-4xl px-4 mx-auto text-center sm:px-6">
                    <h2 class="text-2xl font-bold leading-tight text-white sm:text-3xl md:text-4xl lg:text-5xl">Ready to
                        Elevate Your Event Management?</h2>
                    <p class="mt-3 text-base font-medium text-indigo-100 sm:mt-4 sm:text-lg md:text-xl">Join Gatherly
                        today and experience the future of event planning!</p>
                    <div class="flex flex-col items-center justify-center gap-3 mt-6 sm:mt-8 sm:flex-row sm:gap-4">
                        <a href="public/pages/signup.php"
                            class="inline-block w-full px-6 py-3 text-base font-semibold text-indigo-600 transition-all bg-white shadow-xl sm:w-auto sm:px-8 sm:py-4 sm:text-lg rounded-xl hover:bg-gray-50 hover:shadow-2xl hover:-translate-y-1">
                            Get Started Now
                        </a>
                        <a href="public/pages/venues.php"
                            class="inline-block w-full px-6 py-3 text-base font-semibold text-white transition-all border-2 sm:w-auto sm:px-8 sm:py-4 sm:text-lg bg-white/10 backdrop-blur-sm border-white/30 rounded-xl hover:bg-white/20">
                            Check Our Venues
                        </a>
                    </div>
                </div>
            </div>
            <?php include 'src/components/Footer.php'; ?>
        </div>
    </div>

    <script src="public/assets/js/home.js"></script>
</body>

</html>
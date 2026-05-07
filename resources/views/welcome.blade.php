<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>NexaCampus - Modern Academic Information System</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />
    
    <!-- Styles / Scripts -->
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <style>
            /*! tailwindcss v4.0.7 | MIT License | https://tailwindcss.com */
            @layer theme{:root,:host{--font-sans:'Inter',ui-sans-serif,system-ui,sans-serif,"Apple Color Emoji","Segoe UI Emoji","Segoe UI Symbol","Noto Color Emoji"}}
        </style>
    @endif
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-white dark:bg-gray-900 font-sans antialiased">
    <!-- Navigation -->
    <nav class="fixed w-full bg-white/80 dark:bg-gray-900/80 backdrop-blur-md z-50 border-b border-gray-200 dark:border-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <span class="text-2xl font-bold bg-gradient-to-r from-primary-600 to-blue-600 bg-clip-text text-transparent">
                            NexaCampus
                        </span>
                    </div>
                </div>
                <div class="hidden md:flex items-center space-x-8">
                    <a href="#features" class="text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 transition">Features</a>
                    <a href="#roles" class="text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 transition">Roles</a>
                    <a href="#workflow" class="text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 transition">Workflow</a>
                    @if (Route::has('login'))
                        @auth
                            <a href="{{ url('/dashboard') }}" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-2 rounded-lg font-medium transition">
                                Dashboard
                            </a>
                        @else
                            <a href="{{ route('auth.signin-index') }}" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-2 rounded-lg font-medium transition">
                                Login
                            </a>
                        @endauth
                    @endif
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="pt-32 pb-20 bg-gradient-to-br from-primary-50 via-white to-blue-50 dark:from-gray-900 dark:via-gray-900 dark:to-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div class="space-y-8">
                    <div class="inline-flex items-center px-4 py-2 bg-primary-100 dark:bg-primary-900/30 rounded-full">
                        <span class="text-primary-700 dark:text-primary-300 text-sm font-medium">🚀 Powered by Laravel 12 & Livewire 4</span>
                    </div>
                    <h1 class="text-5xl lg:text-6xl font-extrabold text-gray-900 dark:text-white leading-tight">
                        Modern Academic
                        <span class="block bg-gradient-to-r from-primary-600 to-blue-600 bg-clip-text text-transparent">
                            Information System
                        </span>
                    </h1>
                    <p class="text-xl text-gray-600 dark:text-gray-300 leading-relaxed">
                        Streamline your university operations with our comprehensive platform for student management, course scheduling, attendance tracking, and academic performance monitoring.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4">
                        <a href="{{ route('auth.signin-index') }}" class="inline-flex items-center justify-center px-8 py-4 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transition transform hover:-translate-y-1">
                            Get Started
                            <svg class="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                            </svg>
                        </a>
                        <a href="#features" class="inline-flex items-center justify-center px-8 py-4 bg-white dark:bg-gray-800 text-gray-900 dark:text-white font-semibold rounded-xl border-2 border-gray-300 dark:border-gray-700 hover:border-primary-600 dark:hover:border-primary-400 transition">
                            Learn More
                        </a>
                    </div>
                    <div class="flex items-center space-x-8 pt-4">
                        <div>
                            <div class="text-3xl font-bold text-gray-900 dark:text-white">10+</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Core Features</div>
                        </div>
                        <div class="w-px h-12 bg-gray-300 dark:bg-gray-700"></div>
                        <div>
                            <div class="text-3xl font-bold text-gray-900 dark:text-white">3</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">User Roles</div>
                        </div>
                        <div class="w-px h-12 bg-gray-300 dark:bg-gray-700"></div>
                        <div>
                            <div class="text-3xl font-bold text-gray-900 dark:text-white">PWA</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Ready</div>
                        </div>
                    </div>
                </div>
                <div class="relative">
                    <div class="absolute inset-0 bg-gradient-to-r from-primary-400 to-blue-400 rounded-3xl blur-3xl opacity-20 animate-pulse"></div>
                    <div class="relative bg-white dark:bg-gray-800 rounded-3xl shadow-2xl p-8 border border-gray-200 dark:border-gray-700">
                        <div class="space-y-6">
                            <div class="flex items-center space-x-4">
                                <div class="w-12 h-12 bg-primary-100 dark:bg-primary-900/30 rounded-xl flex items-center justify-center">
                                    <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="font-semibold text-gray-900 dark:text-white">Course Management</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">Complete curriculum control</div>
                                </div>
                            </div>
                            <div class="flex items-center space-x-4">
                                <div class="w-12 h-12 bg-green-100 dark:bg-green-900/30 rounded-xl flex items-center justify-center">
                                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="font-semibold text-gray-900 dark:text-white">Attendance Tracking</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">Real-time session monitoring</div>
                                </div>
                            </div>
                            <div class="flex items-center space-x-4">
                                <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900/30 rounded-xl flex items-center justify-center">
                                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="font-semibold text-gray-900 dark:text-white">Grade Analytics</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">Comprehensive performance insights</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-20 bg-white dark:bg-gray-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">Powerful Features</h2>
                <p class="text-xl text-gray-600 dark:text-gray-400 max-w-3xl mx-auto">
                    Everything you need to manage your academic institution efficiently
                </p>
            </div>
            
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div class="group p-8 bg-gray-50 dark:bg-gray-800 rounded-2xl hover:shadow-xl transition duration-300 border border-gray-200 dark:border-gray-700">
                    <div class="w-14 h-14 bg-blue-100 dark:bg-blue-900/30 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 transition">
                        <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-3">Campus Management</h3>
                    <p class="text-gray-600 dark:text-gray-400">Manage faculties, study programs, buildings, and rooms with ease.</p>
                </div>

                <!-- Feature 2 -->
                <div class="group p-8 bg-gray-50 dark:bg-gray-800 rounded-2xl hover:shadow-xl transition duration-300 border border-gray-200 dark:border-gray-700">
                    <div class="w-14 h-14 bg-green-100 dark:bg-green-900/30 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 transition">
                        <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-3">Student Registration</h3>
                    <p class="text-gray-600 dark:text-gray-400">Streamlined registration process per academic year and period.</p>
                </div>

                <!-- Feature 3 -->
                <div class="group p-8 bg-gray-50 dark:bg-gray-800 rounded-2xl hover:shadow-xl transition duration-300 border border-gray-200 dark:border-gray-700">
                    <div class="w-14 h-14 bg-purple-100 dark:bg-purple-900/30 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 transition">
                        <svg class="w-7 h-7 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-3">Course Planning (KRS)</h3>
                    <p class="text-gray-600 dark:text-gray-400">Digital study plan management with detailed course selection.</p>
                </div>

                <!-- Feature 4 -->
                <div class="group p-8 bg-gray-50 dark:bg-gray-800 rounded-2xl hover:shadow-xl transition duration-300 border border-gray-200 dark:border-gray-700">
                    <div class="w-14 h-14 bg-yellow-100 dark:bg-yellow-900/30 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 transition">
                        <svg class="w-7 h-7 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-3">Class Scheduling</h3>
                    <p class="text-gray-600 dark:text-gray-400">Automated course offerings and schedule management.</p>
                </div>

                <!-- Feature 5 -->
                <div class="group p-8 bg-gray-50 dark:bg-gray-800 rounded-2xl hover:shadow-xl transition duration-300 border border-gray-200 dark:border-gray-700">
                    <div class="w-14 h-14 bg-red-100 dark:bg-red-900/30 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 transition">
                        <svg class="w-7 h-7 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-3">Attendance System</h3>
                    <p class="text-gray-600 dark:text-gray-400">Session-based attendance tracking with real-time records.</p>
                </div>

                <!-- Feature 6 -->
                <div class="group p-8 bg-gray-50 dark:bg-gray-800 rounded-2xl hover:shadow-xl transition duration-300 border border-gray-200 dark:border-gray-700">
                    <div class="w-14 h-14 bg-indigo-100 dark:bg-indigo-900/30 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 transition">
                        <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-3">Grading System</h3>
                    <p class="text-gray-600 dark:text-gray-400">Comprehensive grade components with weighted calculations.</p>
                </div>

                <!-- Feature 7 -->
                <div class="group p-8 bg-gray-50 dark:bg-gray-800 rounded-2xl hover:shadow-xl transition duration-300 border border-gray-200 dark:border-gray-700">
                    <div class="w-14 h-14 bg-teal-100 dark:bg-teal-900/30 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 transition">
                        <svg class="w-7 h-7 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-3">Transcript Generation</h3>
                    <p class="text-gray-600 dark:text-gray-400">Automatic transcript building with GPA/IPK calculations.</p>
                </div>

                <!-- Feature 8 -->
                <div class="group p-8 bg-gray-50 dark:bg-gray-800 rounded-2xl hover:shadow-xl transition duration-300 border border-gray-200 dark:border-gray-700">
                    <div class="w-14 h-14 bg-pink-100 dark:bg-pink-900/30 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 transition">
                        <svg class="w-7 h-7 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-3">Role-Based Access</h3>
                    <p class="text-gray-600 dark:text-gray-400">Granular permissions for admins, lecturers, and students.</p>
                </div>

                <!-- Feature 9 -->
                <div class="group p-8 bg-gray-50 dark:bg-gray-800 rounded-2xl hover:shadow-xl transition duration-300 border border-gray-200 dark:border-gray-700">
                    <div class="w-14 h-14 bg-orange-100 dark:bg-orange-900/30 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 transition">
                        <svg class="w-7 h-7 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-3">PWA Support</h3>
                    <p class="text-gray-600 dark:text-gray-400">Progressive Web App for mobile-friendly access anywhere.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- User Roles Section -->
    <section id="roles" class="py-20 bg-gray-50 dark:bg-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">Built for Everyone</h2>
                <p class="text-xl text-gray-600 dark:text-gray-400 max-w-3xl mx-auto">
                    Tailored experiences for each user role in your institution
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <!-- Admin Role -->
                <div class="bg-white dark:bg-gray-900 rounded-2xl p-8 shadow-lg border border-gray-200 dark:border-gray-700 hover:shadow-2xl transition">
                    <div class="w-16 h-16 bg-primary-100 dark:bg-primary-900/30 rounded-2xl flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">Administrator</h3>
                    <ul class="space-y-3 text-gray-600 dark:text-gray-400">
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>Full system configuration</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>User & permission management</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>Academic data oversight</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>Menu & resource control</span>
                        </li>
                    </ul>
                </div>

                <!-- Lecturer Role -->
                <div class="bg-white dark:bg-gray-900 rounded-2xl p-8 shadow-lg border border-gray-200 dark:border-gray-700 hover:shadow-2xl transition">
                    <div class="w-16 h-16 bg-green-100 dark:bg-green-900/30 rounded-2xl flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">Lecturer</h3>
                    <ul class="space-y-3 text-gray-600 dark:text-gray-400">
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>Manage assigned courses</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>Create attendance sessions</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>Input student grades</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>View student profiles</span>
                        </li>
                    </ul>
                </div>

                <!-- Student Role -->
                <div class="bg-white dark:bg-gray-900 rounded-2xl p-8 shadow-lg border border-gray-200 dark:border-gray-700 hover:shadow-2xl transition">
                    <div class="w-16 h-16 bg-purple-100 dark:bg-purple-900/30 rounded-2xl flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M12 14l9-5-9-5-9 5 9 5z"/>
                            <path d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">Student</h3>
                    <ul class="space-y-3 text-gray-600 dark:text-gray-400">
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>Online registration</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>Digital course planning</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>View grades & transcripts</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>Check schedules & attendance</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Workflow Section -->
    <section id="workflow" class="py-20 bg-white dark:bg-gray-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">How It Works</h2>
                <p class="text-xl text-gray-600 dark:text-gray-400 max-w-3xl mx-auto">
                    Simple workflow from setup to graduation
                </p>
            </div>

            <div class="relative">
                <div class="hidden md:block absolute left-1/2 transform -translate-x-1/2 h-full w-1 bg-gradient-to-b from-primary-500 to-blue-500"></div>
                
                <div class="space-y-12">
                    <!-- Step 1 -->
                    <div class="relative flex items-center">
                        <div class="flex-1 md:text-right md:pr-12">
                            <div class="bg-gray-50 dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700">
                                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">1. Setup & Configuration</h3>
                                <p class="text-gray-600 dark:text-gray-400">Admin configures academic years, faculties, study programs, and curriculum structure.</p>
                            </div>
                        </div>
                        <div class="hidden md:flex absolute left-1/2 transform -translate-x-1/2 w-12 h-12 bg-primary-600 rounded-full items-center justify-center text-white font-bold text-lg">1</div>
                        <div class="flex-1 md:pl-12"></div>
                    </div>

                    <!-- Step 2 -->
                    <div class="relative flex items-center">
                        <div class="flex-1 md:pr-12"></div>
                        <div class="hidden md:flex absolute left-1/2 transform -translate-x-1/2 w-12 h-12 bg-primary-600 rounded-full items-center justify-center text-white font-bold text-lg">2</div>
                        <div class="flex-1 md:pl-12">
                            <div class="bg-gray-50 dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700">
                                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">2. Course Offering</h3>
                                <p class="text-gray-600 dark:text-gray-400">Create course offerings with schedules, assign lecturers, and set up classrooms.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div class="relative flex items-center">
                        <div class="flex-1 md:text-right md:pr-12">
                            <div class="bg-gray-50 dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700">
                                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">3. Student Registration</h3>
                                <p class="text-gray-600 dark:text-gray-400">Students register for the academic period and create their study plans (KRS).</p>
                            </div>
                        </div>
                        <div class="hidden md:flex absolute left-1/2 transform -translate-x-1/2 w-12 h-12 bg-primary-600 rounded-full items-center justify-center text-white font-bold text-lg">3</div>
                        <div class="flex-1 md:pl-12"></div>
                    </div>

                    <!-- Step 4 -->
                    <div class="relative flex items-center">
                        <div class="flex-1 md:pr-12"></div>
                        <div class="hidden md:flex absolute left-1/2 transform -translate-x-1/2 w-12 h-12 bg-primary-600 rounded-full items-center justify-center text-white font-bold text-lg">4</div>
                        <div class="flex-1 md:pl-12">
                            <div class="bg-gray-50 dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700">
                                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">4. Teaching & Attendance</h3>
                                <p class="text-gray-600 dark:text-gray-400">Lecturers conduct classes, create attendance sessions, and track student participation.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Step 5 -->
                    <div class="relative flex items-center">
                        <div class="flex-1 md:text-right md:pr-12">
                            <div class="bg-gray-50 dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700">
                                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">5. Assessment & Grading</h3>
                                <p class="text-gray-600 dark:text-gray-400">Input grades with multiple components, calculate final scores automatically.</p>
                            </div>
                        </div>
                        <div class="hidden md:flex absolute left-1/2 transform -translate-x-1/2 w-12 h-12 bg-primary-600 rounded-full items-center justify-center text-white font-bold text-lg">5</div>
                        <div class="flex-1 md:pl-12"></div>
                    </div>

                    <!-- Step 6 -->
                    <div class="relative flex items-center">
                        <div class="flex-1 md:pr-12"></div>
                        <div class="hidden md:flex absolute left-1/2 transform -translate-x-1/2 w-12 h-12 bg-primary-600 rounded-full items-center justify-center text-white font-bold text-lg">6</div>
                        <div class="flex-1 md:pl-12">
                            <div class="bg-gray-50 dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700">
                                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">6. Transcript & Results</h3>
                                <p class="text-gray-600 dark:text-gray-400">Generate official transcripts with GPA calculations and academic standing.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Tech Stack Section -->
    <section class="py-20 bg-gradient-to-br from-primary-600 to-blue-700">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold text-white mb-4">Built with Modern Technology</h2>
                <p class="text-xl text-primary-100 max-w-3xl mx-auto">
                    Leveraging the latest web technologies for optimal performance
                </p>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <div class="bg-white/10 backdrop-blur-sm rounded-xl p-6 text-center border border-white/20">
                    <div class="text-4xl mb-3">🐘</div>
                    <div class="text-white font-semibold">Laravel 12</div>
                </div>
                <div class="bg-white/10 backdrop-blur-sm rounded-xl p-6 text-center border border-white/20">
                    <div class="text-4xl mb-3">⚡</div>
                    <div class="text-white font-semibold">Livewire 4</div>
                </div>
                <div class="bg-white/10 backdrop-blur-sm rounded-xl p-6 text-center border border-white/20">
                    <div class="text-4xl mb-3">🎨</div>
                    <div class="text-white font-semibold">Tailwind CSS</div>
                </div>
                <div class="bg-white/10 backdrop-blur-sm rounded-xl p-6 text-center border border-white/20">
                    <div class="text-4xl mb-3">📊</div>
                    <div class="text-white font-semibold">PowerGrid</div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-20 bg-gray-50 dark:bg-gray-800">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-4xl font-bold text-gray-900 dark:text-white mb-6">Ready to Transform Your Institution?</h2>
            <p class="text-xl text-gray-600 dark:text-gray-400 mb-8">
                Join the future of academic management with NexaCampus
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('auth.signin-index') }}" class="inline-flex items-center justify-center px-8 py-4 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transition transform hover:-translate-y-1">
                    Start Using Now
                    <svg class="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                    </svg>
                </a>
                <a href="#features" class="inline-flex items-center justify-center px-8 py-4 bg-white dark:bg-gray-900 text-gray-900 dark:text-white font-semibold rounded-xl border-2 border-gray-300 dark:border-gray-700 hover:border-primary-600 dark:hover:border-primary-400 transition">
                    Explore Features
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-3 gap-8 mb-8">
                <div>
                    <h3 class="text-2xl font-bold bg-gradient-to-r from-primary-400 to-blue-400 bg-clip-text text-transparent mb-4">
                        NexaCampus
                    </h3>
                    <p class="text-gray-400">Modern Academic Information System built with Laravel 12 and Livewire 4.</p>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Quick Links</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="#features" class="hover:text-white transition">Features</a></li>
                        <li><a href="#roles" class="hover:text-white transition">User Roles</a></li>
                        <li><a href="#workflow" class="hover:text-white transition">How It Works</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Technology</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li>Laravel 12</li>
                        <li>Livewire 4</li>
                        <li>Tailwind CSS</li>
                        <li>PowerGrid</li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-gray-800 pt-8 text-center text-gray-400">
                <p>&copy; {{ date('Y') }} NexaCampus. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Smooth Scroll Script -->
    <script>
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>

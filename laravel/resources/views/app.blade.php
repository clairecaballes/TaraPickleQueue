<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#0b1426">
        <meta name="description" content="Pickle Ta Bai! — smart, fair & simple pickleball court queue management. Live queues, on-deck boards, fair matchmaking and cute critters.">

        <title>{{ config('app.name', 'Pickle Ta Bai!') }}</title>

        <!-- Social cards -->
        <meta property="og:site_name" content="Pickle Ta Bai!">
        <meta property="og:title" content="Pickle Ta Bai! — Fair pickleball court queues">
        <meta property="og:description" content="Scan to view the live queue: who's on deck, your spot in line, and the court leaderboard — updated in real time.">
        <meta property="og:type" content="website">
        <meta name="twitter:card" content="summary">
        <meta name="twitter:title" content="Pickle Ta Bai! — Fair pickleball court queues">
        <meta name="twitter:description" content="Live pickleball queues, on-deck boards and court leaderboards.">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div id="app"></div>
    </body>
</html>

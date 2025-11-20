<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
</head>

<body>
    @include('emails.coachAssigned', [
        'coach' => (object) ['name' => 'Coach', 'url' => 'test'],
        'user' => (object) ['first_name' => 'Test', 'last_name' => 'User'],
    ])
    <br>
    @include('emails.coachAssignment', [
        'coach' => (object) ['name' => 'Coach', 'url' => 'test'],
        'user' => (object) ['first_name' => 'Test', 'last_name' => 'User'],
        'users' => collect([
            (object) ['first_name' => 'Test', 'last_name' => 'User'],
            (object) ['first_name' => 'Test', 'last_name' => 'User 2'],
            (object) ['first_name' => 'Test', 'last_name' => 'User 3'],
        ]),

        'organizations' => collect([
            ['name' => 'Test Organization'],
            ['name' => 'Test Organization 2'],
            ['name' => 'Test Organization 3'],
        ]),
    ])
    <br>
    @include('emails.credentials', [
        'credential' => [
            'name' => 'User',
            'type' => 'User',
            'email' => 'User@gmail.com',
            'password' => '123456',
            'role' => 'User',
            'organization' => 'Test Organization',
        ],
    ])


    <br>@include('emails.newOnBoarding', [
        'coach' => ['name' => 'Coach'],
        'organization' => ['name' => 'Organization'],
    ])


    <br>@include('emails.organizationAssigned', [
        'coach' => ['name' => 'Coach'],
        'organization' => ['name' => 'Organization'],
    ])


    <br>@include('emails.organizationSubmission', [
        'coach' => ['name' => 'Coach'],
        'organization' => [
            'name' => 'Organization',
            'poc_name' => 'POC Name',
            'url' => 'url',
            'token' => 'token',
        ],
    ])


    <br>@include('emails.organizationSubmitted', [
        'admin' => ['name' => 'Admin'],
        'organization' => ['name' => 'Organization'],
    ])


    <br>@include('emails.planAssigned', [
        'user' => [
            'first_name' => 'Test',
            'last_name' => 'User',
        ],
        'plan' => [
            'start_date' => '2024-02-02',
            'end_date' => '2024-02-02',
            'title' => 'Plan',
        ],
    ])


    <br>@include('emails.reminder', [
        'user' => [
            'first_name' => 'Test',
            'name' => 'Test',
            'last_name' => 'User',
            'email' => 'user@gmail.com',
            <!-- SECURITY: Password reset link should be sent instead of plaintext password -->
        ],
    ])


    <br>@include('emails.resetPassword', [
        'user' => [
            'first_name' => 'Test',
            'name' => 'Test',
            'reset_token' => 'token',
            'last_name' => 'User',
            'email' => 'user@gmail.com',
            <!-- SECURITY: Password reset link should be sent instead of plaintext password -->
        ],
        'type' => 'admin',
    ])


    <br>@include('emails.sendAdminSignupMail', [
        'name' => 'Admin',
        'user' => [
            'first_name' => 'Test',
            'name' => 'Test',
            'reset_token' => 'token',
            'last_name' => 'User',
            'email' => 'user@gmail.com',
            <!-- SECURITY: Password reset link should be sent instead of plaintext password -->
        ],
    ])
    <br>@include('emails.sendForgotOtp', [
        'name' => 'Test User',
        'user' => [
            'otp' => '123456',
        ],
    ])


    <br>@include('emails.sendOtp', [
        'name' => 'Test User',
        'user' => [
            'otp' => '123456',
        ],
    ])
    <br>@include('emails.sendUserWelcomeMail', [
        'user' => [
            'first_name' => 'Test',
            'name' => 'Test',
            'reset_token' => 'token',
            'last_name' => 'User',
            'email' => 'user@gmail.com',
            <!-- SECURITY: Password reset link should be sent instead of plaintext password -->
        ],
    ])
    <br>@include('emails.userAssignment', [
        'user' => (object) [
            'first_name' => 'Test',
            'name' => 'Test',
            'reset_token' => 'token',
            'last_name' => 'User',
            'email' => 'user@gmail.com',
            <!-- SECURITY: Password reset link should be sent instead of plaintext password -->
        ],
        'coach' => (object) ['name' => 'Coach'],
    ])

</body>

</html>

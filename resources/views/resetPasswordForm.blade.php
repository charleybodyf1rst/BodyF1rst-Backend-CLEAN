<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
    <!-- Bootstrap CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons CDN (Optional) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/3.3.0/mdb.min.css" rel="stylesheet">

    <style>
        .login-page {
            background: #ffffff;
            height: 100vh;
            width: 100%;
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .form-box {
            background: #ffffff;
            width: 400px;
            border-radius: 38px;
            padding: 30px;
        }

        .form-logo {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .form-logo img {
            width: 150px;
            max-width: 100%;
            height: auto;
        }

        .inner-form h1 {
            font-size: 20px;
            font-family: 'Monstserrat, san-serif';
            font-weight: 500;
            color: #212529;
            text-align: center;
            padding-top: 15px;
        }

        .form-control::placeholder {
            color: #000000;
            font-size: 14px;
            font-weight: 400;
        }

        .input-field {
            display: flex;
            align-items: center;
            position: relative;
            margin-top: 25px;
        }

        .form-control {
            flex: 1;
            padding-right: 100px;
            font-size: 14px;
            background: #fafafa;
            border-radius: 11px;
            height: 40px;
        }

        .forgot-password {
            position: absolute;
            right: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 400;
            color: #ff3c0b;
            text-decoration: none;
        }

        .form-btn {
            height: 40px;
            width: 100%;
            background: #ff3c0b;
            color: #ffffff;
            border-radius: 11px;
            text-align: center;
            font-size: 14px;
            font-weight: 400;
            border: none;
            margin-top: 25px;
        }
    </style>

</head>

<body>
    <div class="login-page">
        <div class="form-box">
            <div class="form-logo">
                <img src={{ asset('logo.svg') }} alt="logo" />
            </div>
            <div class="inner-form">
                <h1>{{ __('Reset Password') }}</h1>
                <form method="POST" class="inner-form" action="{{ route('resetPassword.'.$type, ['token' => $token]) }}">
                    @csrf

                    <div class="mb-3 mt-3 form-outline" data-mdb-input-init>
                        <input id="password" type="password"
                            class="form-control @error('password') is-invalid @enderror bg-white" name="password"
                            autocomplete="current-password">
                        <label class="form-label" for="password">{{ __('Password*') }}</label>
                        @error('password')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>
                    <div class="mb-3 mt-5 form-outline" data-mdb-input-init>
                        <input id="password_confirmation" type="password"
                            class="form-control @error('password_confirmation') is-invalid @enderror bg-white"
                            name="password_confirmation" autocomplete="current-password_confirmation">
                        <label class="form-label" for="password_confirmation">{{ __('Confirm Password*') }}</label>
                        @error('password_confirmation')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    <button type="submit" class="form-btn">
                        {{ __('Change Password') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/3.3.0/mdb.min.js"></script>

</body>

</html>

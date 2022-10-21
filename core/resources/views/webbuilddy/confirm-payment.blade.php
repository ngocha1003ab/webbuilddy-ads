<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Payment - Confirm</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:ital,wght@0,300;0,400;0,600;0,700;0,900;1,300;1,400;1,600;1,700;1,900&display=swap"
        rel="stylesheet">
    <style>
        * {
            font-family: 'Source Sans Pro', sans-serif;
        }

        .payment-wrapper {
            max-width: 450px;
            width: 35%;
            background-color: #0086b380;
            margin: auto;
            border-radius: 4px;
            margin-top: 10%;
            box-shadow: 4px 7px 5px -2px rgba(168, 168, 168, 0.37);
            -webkit-box-shadow: 4px 7px 5px -2px rgba(168, 168, 168, 0.37);
            -moz-box-shadow: 4px 7px 5px -2px rgba(168, 168, 168, 0.37);
        }

        .power-authen img {
            width: 120px;
        }

        .power-authen {
            text-align: center;
            padding: 10px;
            margin-top: 20px;
        }

        .payment-title {
            padding: 10px;
            color: white;
        }

        .payment-title h1 {
            margin: 0;
        }

        .payment-item {
            border-bottom: 1px solid;
            font-size: 20px;
        }

        .payment-submit {
            text-align: center;
            margin-top: 50px;
        }

        button {
            padding: 8px 55px;
            font-size: 20px;
            color: white;
            border: none;
            background-color: #1299da;
        }
    </style>
</head>

<body>
    <div class="payment-wrapper">
        <div class="payment-title">
            <h1>Payment Detail</h1>
            <div class="payment-item">
                <p>Price: {{ number_format($amount) }} {{ $gate->currency }}</p>
            </div>
            <div class="payment-item">
                <p>Fee: {{ number_format($charge) }} {{ $gate->currency }}</p>
            </div>
            <div class="payment-item">
                <p>Total Payment: {{ number_format($final_amo) }} {{ $gate->currency }}</p>
            </div>
            <div class="payment-submit">
                <button>Submit</button>
            </div>
        </div>
        <div class="power-authen">
            <a href="https://webbuilddy.com/"><img src="{{ asset('assets/images/logo-no-background-white.png') }}"
                    alt=""></a>
        </div>
    </div>
</body>

</html>

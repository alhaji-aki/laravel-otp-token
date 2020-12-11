# Laravel Otp Token

This is a simple package to help generate otp tokens for users. This package follows the same approach as laravel's password reset functionality.

## Installation

You can install the package via composer by running:

```bash
composer require "alhaji-aki/laravel-otp-token"
```

After the installation has completed, the package will automatically register itself.
Run the following to publish the migration, config and lang file

```bash
php artisan vendor:publish --provider="AlhajiAki\OtpToken\OtpTokenServiceProvider"
```

After publishing the migration you can create the otp_tokens table by running the migrations:

```bash
php artisan migrate
```

The contents of the config file looks like:

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Otp Token Defaults
    |--------------------------------------------------------------------------
    |
    | This option controls the default authentication "guard" and password
    | reset options for your application. You may change these defaults
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'otp_tokens' => 'users',
    ],

    /*
    |--------------------------------------------------------------------------
    | Otp Tokens
    |--------------------------------------------------------------------------
    |
    | You may specify multiple otp token configurations if you have more
    | than one user table or model in the application and you want to have
    | separate otp token settings based on the specific user types.
    |
    | The expire time is the number of seconds that the token should be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    */
    'otp_tokens' => [
        'users' => [
            'provider' => 'users',
            'table' => 'otp_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],
];
```

With this configuration you can have multiple otp token providers just like laravel's password and auth providers.

The lang file that is published looks like:

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Password Reset Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are the default lines which match reasons
    | that are given by the password broker for a password update attempt
    | has failed, such as for an invalid token or invalid new password.
    |
    */

    'completed' => 'We have completed the verification process.',
    'sent' => 'We have sent your otp token.',
    'throttled' => 'Please wait before retrying.',
    'token' => 'This otp token is invalid.',
    'user' => "We can't find the user account.",

];
```

## Usage

The model that u want to generate otp token for should implement the following interface and trait

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use AlhajiAki\OtpToken\Contracts\CanSendOtpToken;
use AlhajiAki\OtpToken\Traits\CanSendOtpToken as CanSendOtpTokenTrait;

class YourModel extends Model implements CanSendOtpToken
{
    use CanSendOtpTokenTrait;
}
```

The `getColumnForOtpToken($field)` is used the get the value of the field that will be associated to an otp token. This comes in handy if for example you want to be able to generate different otp tokens for say a user's email and mobile phone number during verification.

### Generating an otp token

The OtpToken facade exposes a `sendOtpToken()` method which expects two parameters. They are:

-   an array containing the field to use, the action to associate with the token and the value for the field specified.
-   the second parameter is a closure that would be executed after the otp token has been generated. The closure will get an instance of the `CanSendOtpToken` contract and the token that was generated.

The method returns a response which can be one of the following constants :

-   OTP_TOKEN_SENT: When the closure passed executes successfully.
-   INVALID_USER: When the user cannot be found.
-   OTP_TOKEN_THROTTLED: the user has to wait for some minutes before they can create a new token.

A common usage example of the `sendOtpToken()` is when you want to send an otp verification token to a user's phone number. Here is an example implementation:

```php
<?php

namespace App\Http\Controllers;

use AlhajiAki\OtpToken\OtpToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MobileNumberVerificationController extends Controller
{
    public function store(Request $request)
    {
        $response = OtpToken->broker()->sendOtpToken([
            'mobile' => '+233248000000',
            'action' => 'verify-user',
            'field' => 'mobile'
        ], function ($user, $token) {
            $user->notify(new VerifyMobile($token));
        });

        return response()->json([
            'message' => trans($response)
        ], $response == OtpToken::OTP_TOKEN_SENT ? Response::HTTP_OK : Response::HTTP_UNAUTHORIZED);
    }
}

```

The facade also has a `performAction()` method to perform an action when the provided otp token is valid. This method also expects two parameters. They are:

-   an array containing the field to use, this should be the same as the field passed when the token was generated, the action that was associated to the token when it was generated and the value for the field specified.
-   the second parameter is a closure that would be executed after the otp token has been verified. The closure will get an instance of the `CanSendOtpToken` contract.

The method returns a response which can be one of the following constants :

-   ACTION_COMPLETED: When the closure passed executes successfully.
-   INVALID_USER: When the user cannot be found.
-   INVALID_TOKEN: When the token submitted is an invalid one.

A common usage example of the `performAction()` is when you want to verify that the otp token is a corrent one. Here is an example implementation:

```php
<?php

namespace App\Http\Controllers;

use AlhajiAki\OtpToken\OtpToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class MobileNumberVerificationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function verify(Request $request)
    {
        $response = OtpToken::performAction([
                'token' => $request->token,
                'mobile' => $request->user()->mobile,
                'action' => 'verify-user',
                'field' => 'mobile'
            ],
            function ($user) {
                $user->update([
                    'mobile_verified_at' => now()
                ]);
            }
        );

        if ($response !== OtpToken::ACTION_COMPLETED) {
            throw ValidationException::withMessages([
                'token' => trans($response)
            ]);
        }

        return response()->json([
            'message' => trans($response)
        ], Response::HTTP_OK);
    }
}

```

## Testing

```bash
vendor/bin/phpunit
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

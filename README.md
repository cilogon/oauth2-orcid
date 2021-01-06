# ORCID Provider for the OAuth 2.0 Client

[![License](https://img.shields.io/badge/license-NCSA-brightgreen.svg)](https://github.com/cilogon/oauth2-orcid/blob/master/LICENSE)
[![Travis](https://img.shields.io/travis/cilogon/oauth2-orcid/master.svg)](https://travis-ci.org/cilogon/oauth2-orcid)
[![Scrutinizer](https://img.shields.io/scrutinizer/g/cilogon/oauth2-orcid/master.svg)](https://scrutinizer-ci.com/g/cilogon/oauth2-orcid/)
[![Coveralls](https://img.shields.io/coveralls/cilogon/oauth2-orcid/master.svg)](https://coveralls.io/github/cilogon/oauth2-orcid?branch=master)

This package provides ORCID OAuth 2.0 support for the PHP League's [OAuth 2.0 Client](https://github.com/thephpleague/oauth2-client).

[ORCID](https://orcid.org) provides a persistent digital identifier for researchers. See [Getting Started](https://members.orcid.org/api/getting-started) for information on integrating your application with ORCID. You will eventually need to [register your application](https://orcid.org/developer-tools) to get an ORCID client id and client secret for your integration.

This package is compliant with [PSR-1][], [PSR-4][] and [PSR-12][]. If you notice compliance oversights, please send a patch via pull request.

[PSR-1]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md
[PSR-4]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md
[PSR-12]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-12-extended-coding-style-guide.md

## Requirements

The following versions of PHP are supported.

* PHP 7.1
* PHP 7.2
* PHP 7.3

## Installation

To install, use composer:

```
composer require cilogon/oauth2-orcid
```

## Usage

### Authorization Code Flow

```php
$provider = new CILogon\OAuth2\Client\Provider\ORCID([
    'clientId'     => '{orcid-client-id}',
    'clientSecret' => '{orcid-client-secret}',
    'redirectUri'  => 'https://example.com/callback-url',
]);

if (!empty($_GET['error'])) {

    // Got an error, probably user denied access
    exit('Got error: ' . $_GET['error'] . 
         'Description: ' . $GET['error_description']);

} elseif (empty($_GET['code'])) {

    $authUrl = $provider->getAuthorizationUrl();
    $_SESSION['oauth2state'] = $provider->getState();
    header('Location: '.$authUrl);
    exit;

} elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {

    // Check given state against previously stored one to mitigate CSRF attack
    unset($_SESSION['oauth2state']);
    exit('Invalid state');

} else {

    try {
        // Try to get an access token using the authorization code grant
        $token = $provider->getAccessToken('authorization_code', [
            'code' => $_GET['code']
        ]);

        // Print out the access token, which can be used in 
        // authenticated requests against the service provider's API.
        echo '<xmp>' . "\n";
        echo 'Token                  : ' . $token->getToken() . "\n";
        $expires = $token->getExpires();
        if (!is_null($expires)) {
            echo 'Expires                : ' . $token->getExpires();
            echo ($token->hasExpired() ? ' (expired)' : ' (active)') . "\n";
        }
        echo '</xmp>' . "\n";

        // Using the access token, get the user's details
        $user = $provider->getResourceOwner($token);

        echo '<xmp>' . "\n";
        echo 'User ID                : ' . $user->getId() . "\n";
        echo 'First name             : ' . $user->getGivenName() . "\n";   // or getFirstName()
        echo 'Last name              : ' . $user->getFamilyName() . "\n";  // or getLastName()
        echo 'Published name         : ' . $user->getName() . "\n";
        echo 'Also Known As          : ' . implode(',', $user->getOtherNames()) . "\n";
        echo 'Email                  : ' . $user->getEmail() . "\n";       // 'Primary' preferred
        echo 'Primary Email          : ' . $user->getPrimaryEmail() . "\n";// 'Primary' ONLY
        echo 'All Emails             : ' . implode(',', $user->getEmails()) . "\n";
        echo '</xmp>';

    } catch (Exception $e) {

        // Failed to get access token or user details
        exit('Something went wrong: ' . $e->getMessage());

    }
}
```

### Sandbox vs Production

In order to authenticate ORCID users and read associated attributes, you would typically use the Production Registry. However, for special integrations, you may want to register for a [Sandbox application](https://orcid.org/content/register-client-application-sandbox). To use the Sandbox
environment, set a 'sandbox' parameter to 'true' when creating the provider.

```php
$provider = new CILogon\OAuth2\Client\Provider\ORCID([
    'clientId'     => '{orcid-client-id}',
    'clientSecret' => '{orcid-client-secret}',
    'redirectUri'  => 'https://example.com/callback-url',
    'sandbox'      => true
]);
```

Note that you can use this in combination with the Member API (below).


### Public API vs Member API

If you are an [ORCID member](https://orcid.org/about/membership), you can use the Member API instead of the Public API. To use the Member API, set a 'member' parameter to 'true' when creating the provider.

```php
$provider = new CILogon\OAuth2\Client\Provider\ORCID([
    'clientId'     => '{orcid-client-id}',
    'clientSecret' => '{orcid-client-secret}',
    'redirectUri'  => 'https://example.com/callback-url',
    'member'       => true
]);

````

Note that you can use this in combination with the Sandbox environment (above).

As an [ORCID member](https://orcid.org/about/membership) you will get an information about user authentication method reference('mfa' for users who have enabled two-factor authentication on their ORCID account, and 'pwd' for users who haven’t)

### Refreshing a Token

[Refreshing an ORCID token](https://members.orcid.org/api/oauth/refresh-tokens) requires the value of the current access token as the Bearer Token for authentication. So your application needs to use both the current access token AND the refresh token to get a new access token (and associated refresh token).

```php
$accesstoken  = $token->getToken();
$refreshtoken = $token->getRefreshToken();
$newtoken = $provider->getAccessToken('refresh_token', [
    'refresh_token' => $refreshtoken,
], $accesstoken);
```

## License

The University of Illinois/NCSA Open Source License (NCSA). Please see [License File](https://github.com/cilogon/oauth2-orcid/blob/master/LICENSE) for more information.

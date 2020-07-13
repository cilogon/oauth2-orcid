<?php

/**
 * This file is part of the cilogon/oauth2-orcid library.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author    Terry Fleury <tfleury@cilogon.org>
 * @copyright 2016 University of Illinois
 * @license   https://opensource.org/licenses/NCSA NCSA
 * @link      https://github.com/cilogon/oauth2-orcid GitHub
 */

namespace CILogon\OAuth2\Client\Provider;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;

class ORCID extends AbstractProvider
{
    use BearerAuthorizationTrait;

    /**
     * @var string Key used in the access token response to identify the resource owner.
     */
    public const ACCESS_TOKEN_RESOURCE_OWNER_ID = 'orcid';

    /**
     * @var string
     */
    public $sandbox = false;

    /**
     * @var string
     */
    public $member = false;

    /**
     * Returns the base URL for authorizing a client.
     *
     * @return string
     */
    public function getBaseAuthorizationUrl()
    {
        return 'https://' .
            (($this->sandbox) ? 'sandbox.' : '') .
            'orcid.org/oauth/authorize';
    }

    /**
     * Returns the base URL for requesting an access token.
     *
     * @param array $params
     * @return string
     */
    public function getBaseAccessTokenUrl(array $params)
    {
        return 'https://' .
            (($this->sandbox) ? 'sandbox.' : '') .
            'orcid.org/oauth/token';
    }

    /**
     * Returns the URL for requesting the resource owner's details.
     *
     * @param AccessToken $token
     *
     * @return string
     */
    public function getResourceOwnerDetailsUrl(AccessToken $token)
    {
        return 'https://' .
            (($this->member) ? 'api.' : 'pub.') .
            (($this->sandbox) ? 'sandbox.' : '') .
            'orcid.org/v2.0/' .
            $token->getResourceOwnerId() .
            '/record';
    }

    /**
     * Returns the default scopes used by this provider.
     *
     * This should only be the scopes that are required to request the details
     * of the resource owner, rather than all the available scopes.
     *
     * @return array
     */
    protected function getDefaultScopes()
    {
        return [ '/authenticate' ];
    }

    /**
     * Returns the default headers used by this provider.
     *
     * Typically this is used to set 'Accept' or 'Content-Type' headers.
     *
     * @return array
     */
    protected function getDefaultHeaders()
    {
        return [ 'Accept' => 'application/json' ];
    }


    /**
     * Check a provider response for errors.
     *
     * @throws IdentityProviderException
     * @param  ResponseInterface $response
     * @param  string $data Parsed response data
     * @return void
     */
    protected function checkResponse(ResponseInterface $response, $data)
    {
        $error = false;
        $errcode = 0;
        $errmsg = '';

        if (!empty($data['error'])) {
            $error = true;
            $errmsg = $data['error'];
            if (!empty($data['error_description'])) {
                $errmsg .= ': ' . $data['error_description'];
            }
        } elseif (!empty($data['error-code'])) {
            $error = true;
            $errcode = (int)$data['error-code'];
            if (!empty($data['developer-message'])) {
                $errmsg = $data['developer-message'];
            }
        } elseif ($response->getStatusCode() >= 400) {
            $error = true;
            $errcode = $response->getStatusCode();
            $errmsg = $response->getReasonPhrase();
        }

        if ($error) {
            throw new IdentityProviderException($errmsg, $errcode, $data);
        }
    }

    /**
     * Generate a user object from a successful user details request.
     *
     * @param object $response
     * @param AccessToken $token
     * @return ORCIDResourceOwner
     */
    protected function createResourceOwner(array $response, AccessToken $token)
    {
        return new ORCIDResourceOwner($response);
    }

    /**
     * Returns a prepared request for requesting an access token.
     *
     * @param array $params Query string parameters
     * @return RequestInterface
     */
    protected function getAccessTokenRequest(array $params, $accesstoken = null)
    {
        $method  = $this->getAccessTokenMethod();
        $url     = $this->getAccessTokenUrl($params);
        $options = $this->optionProvider->getAccessTokenOptions($method, $params);
        if (is_null($accesstoken)) {
            return $this->getRequest($method, $url, $options);
        } else {
            return $this->getAuthenticatedRequest($method, $url, $accesstoken, $options);
        }
    }
    /**
     * Requests an access token using a specified grant and option set.
     *
     * @param  mixed $grant
     * @param  array $options
     * @return AccessToken
     */
    public function getAccessToken($grant, array $options = [], $accesstoken = null)
    {
        $grant = $this->verifyGrant($grant);
        $params = [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri'  => $this->redirectUri,
        ];
        $params   = $grant->prepareRequestParameters($params, $options);
        $request  = $this->getAccessTokenRequest($params, $accesstoken);
        $response = $this->getParsedResponse($request);
        $prepared = $this->prepareAccessTokenResponse($response);
        $token    = $this->createAccessToken($prepared, $grant);
        return $token;
    }
}

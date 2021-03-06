<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Common\Config;
use LC\Common\Http\BeforeHookInterface;
use LC\Common\Http\Exception\HttpException;
use LC\Common\Http\RedirectResponse;
use LC\Common\Http\Request;
use LC\Common\Http\Response;
use LC\Common\Http\Service;
use LC\Common\Http\ServiceModuleInterface;
use LC\Common\Http\SessionInterface;
use LC\Common\Http\UserInfo;
use LC\Common\HttpClient\HttpClientInterface;
use LC\Common\Json;
use LC\Common\TplInterface;

class IrmaAuthentication implements ServiceModuleInterface, BeforeHookInterface
{
    /** @var \LC\Common\TplInterface */
    protected $tpl;

    /** @var SessionInterface */
    private $session;

    /** @var \LC\Common\HttpClient\HttpClientInterface */
    private $httpClient;

    /** @var \LC\Common\Config */
    private $config;

    public function __construct(SessionInterface $session, TplInterface $tpl, HttpClientInterface $httpClient, Config $config)
    {
        $this->session = $session;
        $this->tpl = $tpl;
        $this->httpClient = $httpClient;
        $this->config = $config;
    }

    /**
     * @return void
     */
    public function init(Service $service)
    {
        $service->post(
            '/_irma/verify',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request) {
                if (null === $sessionToken = $this->session->get('_irma_auth_token')) {
                    throw new HttpException('token not found in session', 400);
                }

                $irmaStatusUrl = sprintf('%s/session/%s/result', $this->config->requireString('irmaServerUrl', 'http://localhost:8088'), $sessionToken);
                $httpResponse = $this->httpClient->get($irmaStatusUrl, [], []);
                // @see https://irma.app/docs/api-irma-server/#get-session-token-result
                $jsonData = Json::decode($httpResponse->getBody());
                if (\array_key_exists('error', $jsonData)) {
                    throw new HttpException('Error: '.$jsonData['error'], 401);
                }

                // the "proofStatus" key is only available when the
                // authentication finished, here we make sure it is 'VALID'
                if (!\array_key_exists('proofStatus', $jsonData)) {
                    throw new HttpException('missing "proofStatus"', 401);
                }
                if ('VALID' !== $jsonData['proofStatus']) {
                    throw new HttpException('"proofStatus" MUST be "VALID"', 401);
                }

                $userIdAttribute = $this->config->requireString('userIdAttribute');
                $userId = null;

                // extract the attribute we want
                foreach ($jsonData['disclosed'][0] as $attributeList) {
                    if ($userIdAttribute === $attributeList['id']) {
                        $userId = $attributeList['rawvalue'];
                    }
                }

                if (null === $userId) {
                    throw new HttpException('unable to extract "'.$userIdAttribute.'" from the disclosed attribute(s)', 401);
                }

                $this->session->set('_irma_auth_user', $userId);

                // return to where the users started at
                return new RedirectResponse($request->requireHeader('HTTP_REFERER'), 302);
            }
        );
    }

    /**
     * @return \LC\Common\Http\UserInfo|\LC\Common\Http\Response|null
     */
    public function executeBefore(Request $request, array $hookData)
    {
        if (Service::isWhitelisted($request, ['POST' => ['/_irma/verify']])) {
            return null;
        }

        if (null !== $authUser = $this->session->get('_irma_auth_user')) {
            return new UserInfo(
                $authUser,
                []
            );
        }

        // @see https://irma.app/docs/getting-started/#perform-a-session
        $httpResponse = $this->httpClient->postRaw(
            $this->config->requireString('irmaServerUrl', 'http://localhost:8088').'/session',
            [],
            Json::encode(
                [
                    '@context' => 'https://irma.app/ld/request/disclosure/v2',
                    'disclose' => [
                        [
                            [
                                $this->config->requireString('userIdAttribute'),
                            ],
                        ],
                    ],
                ]
            ),
            [
                'Authorization: '.$this->config->requireString('secretToken'),
                'Content-Type: application/json',
            ]
        );

        $jsonData = Json::decode($httpResponse->getBody());
        if (!\array_key_exists('sessionPtr', $jsonData)) {
            throw new HttpException('"sessionPtr" not available JSON response', 500);
        }
        // extract "token" and store it in the session to be used
        // @ verification stage
        if (!\array_key_exists('token', $jsonData)) {
            throw new HttpException('"token" not available in JSON response', 500);
        }
        $sessionToken = $jsonData['token'];
        $this->session->set('_irma_auth_token', $sessionToken);

        // extract sessionPtr and make available to frontend
        $sessionPtr = Json::encode($jsonData['sessionPtr']);

        $response = new Response(200, 'text/html');
        $response->setBody(
            $this->tpl->render(
                'irmaAuthentication',
                [
                    '_show_logout_button' => false,
                    'sessionPtr' => $sessionPtr,
                ]
            )
        );

        return $response;
    }
}

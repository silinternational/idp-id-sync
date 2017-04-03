<?php
namespace Sil\Idp\IdSync\Behat\Context;

use Behat\Behat\Context\Context;
use GuzzleHttp\Client;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;

/**
 * Defines application features from the specific context.
 */
class WebhookContext implements Context
{
    private $endpoint;
    private $requestBody;
    
    /** @var ResponseInterface */
    private $response;
    
    /**
     * @Given a notification to :endpoint contains :requestBody
     */
    public function aNotificationToContains($endpoint, $requestBody)
    {
        $this->endpoint = $endpoint;
        $this->requestBody = $requestBody;
    }

    /**
     * @When ID Sync receives the notification
     */
    public function idSyncReceivesTheNotification()
    {
        $client = new Client([
            'base_uri' => 'http://app',
            'http_errors' => false, // Don't throw exceptions on 4xx/5xx.
            'headers' => [
                'Authorization' => 'Bearer abc123',
            ],
            'json' => $this->requestBody,
        ]);
        $this->response = $client->post($this->endpoint);
    }

    /**
     * @Then it should return a status code of :responseCode
     */
    public function itShouldReturnAStatusCodeOf($responseCode)
    {
        Assert::assertSame((int)$responseCode, $this->response->getStatusCode());
    }
}

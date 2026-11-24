<?php
namespace Sil\Idp\IdBroker\Client;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Exception\CommandException;
use GuzzleHttp\Command\Guzzle\GuzzleClient;
use GuzzleHttp\Command\Guzzle\Description;
use GuzzleHttp\Command\Guzzle\Handler\ValidatedDescriptionHandler;

class BaseClient extends GuzzleClient
{
    /**
     * @param array $config
     * @throws \Exception
     */
    public function __construct(array $config = [])
    {
        // Ensure that the credentials have been provided.
        if ( ! isset($config['access_token'])) {
            throw new \InvalidArgumentException(
                'You must provide an Access Token.'
            );
        }
        
        // Apply some defaults.
        $mergedConfig = array_replace_recursive($config, [
            /** @todo Changed... find equivalent. */
            //'max_retries' => 3,
            
            'http_client_options' => [
                'http_errors' => false,
                'headers' => [
                    'Authorization' => 'Bearer ' . $config['access_token'],
                ],
            ],
        ]);
        
        // Create the client.
        parent::__construct(
            $this->getHttpClientFromConfig($mergedConfig),
            $this->getDescriptionFromConfig($mergedConfig),
            null,
            null,
            null,
            $mergedConfig
        );
    }

    /**
     * Set up the handler stack the same way GuzzleClient does, except that
     * the description validator is wrapped so that its failures are thrown as
     * a ValidationException.
     *
     * @param array $config
     */
    protected function processConfig(array $config): void
    {
        $validate = !isset($config['validate']) || $config['validate'] === true;
        $config['validate'] = false;
        parent::processConfig($config);

        if ($validate) {
            $this->getHandlerStack()->push(
                self::getValidationMiddleware(
                    new ValidatedDescriptionHandler($this->getDescription())
                ),
                'validate_description'
            );
        }
    }

    /**
     * Wrap the given validator middleware so that any CommandException it
     * throws before passing the command along to the next handler is
     * rethrown as a ValidationException. A CommandException thrown
     * synchronously by a later handler (e.g. command middleware pushed
     * after this one) is rethrown unchanged. Transport errors never reach
     * this catch; ServiceClient returns them as a rejected promise.
     *
     * @param callable $validator
     * @return callable
     */
    private static function getValidationMiddleware(callable $validator): callable
    {
        return function (callable $handler) use ($validator) {
            return function (CommandInterface $command) use ($validator, $handler) {
                $commandWasPassedOn = false;
                $next = function (CommandInterface $command) use ($handler, &$commandWasPassedOn) {
                    $commandWasPassedOn = true;
                    return $handler($command);
                };

                try {
                    return $validator($next)($command);
                } catch (CommandException $e) {
                    if ($commandWasPassedOn) {
                        throw $e;
                    }
                    throw new ValidationException($e->getMessage(), $command, $e);
                }
            };
        };
    }

    /**
     * @param array $config
     * @return HttpClient
     */
    private function getHttpClientFromConfig(array $config): HttpClient
    {
        // If a client was provided, return it.
        if (isset($config['http_client'])) {
            return $config['http_client'];
        }
        
        // Create a Guzzle HttpClient.
        $clientOptions = $config['http_client_options'] ?? [];
        $client = new HttpClient($clientOptions);
        
        // Attach request retry logic.
        /** @todo Changed... find equivalent. */
//        $client->getEmitter()->attach(new RetrySubscriber([
//            'max' => $config['max_retries'],
//            'filter' => RetrySubscriber::createChainFilter([
//                RetrySubscriber::createStatusFilter(),
//                RetrySubscriber::createCurlFilter(),
//            ]),
//        ]));
        
        return $client;
    }

    /**
     * @param array $config
     * @return Description|mixed
     * @throws \Exception
     */
    private function getDescriptionFromConfig(array $config)
    {
        // If a description was provided, return it.
        if (isset($config['description'])) {
            return $config['description'];
        }
        
        // Load service description data.
        $data = is_readable($config['description_path'])
            ? include $config['description_path']
            : [];
        
        // Override description from local config if set
        if (isset($config['description_override'])) {
            $data = array_replace_recursive($data, $config['description_override']);
        }
        
        if ( ! isset($data['baseUri'])) {
            throw new \Exception('A baseUri is required.', 1488211973);
        }
        
        return new Description($data);
    }
}

# GraphQL Websocket PHP

[![Version](https://poser.pugx.org/goalgorilla/graphql-php-ws/version)](//packagist.org/packages/goalgorilla/graphql-php-ws)

A PHP implementation of the [GraphQL over WebSocket Protocol](https://github.com/enisdenjo/graphql-ws/blob/master/PROTOCOL.md)
using [Ratchet](http://socketo.me/).

## Work in Progress

This library is in active development and its interfaces, implementation and 
usage is bound to change. Don't use this if you're not willing to invest the 
time to rewrite the application you build on top of it.

## Usage

```php
<?php

use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQLWs\GraphQLWsSubscriberInterface;
use GraphQLWs\GraphQLWsSubscriptionServer;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsConnection;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory;
use React\Socket\Server as Reactor;
use Symfony\Component\HttpKernel\Log\Logger;

class RedisEventQueue implements GraphQLWsSubscriberInterface {
  
  /**
   * The subscriptions listening to our GraphQL 
   */
  protected array $subscriptions = [];

  /**
   * Called with new subscribers.
   *
   * {@inheritdoc} 
   */  
  public function onSubscribe(string $subscription_id, WsConnection $client, OperationDefinitionNode $query, ?string $operationName = NULL, ?array $variables = NULL) : void{
    $this->subscriptions[$subscription_id] = [
      'client' => $client,
      'query' => $query,
      'operationName' => $operationName,
      'variables' => $variables,
    ];
  }
  
  /**
   * Called when connections are closed.
   *
   * {@inheritdoc} 
   */  
  public function onComplete(string $subscription_id) : void{
    unset($this->subscriptions[$subscription_id]);
  }
  
  /**
   * Example function receiving data from an event system (e.g. a Redis queue). 
   */
  public function onData($data) {
    // For this example we simply naïvely write the data to the client. This is
    // most certainly a GraphQL error. This is where you'd actually resolve the
    // queries your subscribers are subscribed to with the new data. This can be
    // made easier by doing some double bookkeeping in the onSubscribe event
    // handler but that's out of the scope of this example.  
    foreach ($this->subscriptions as $subscription) {
      $subscription['client']->send(json_encode($data));
    }
  }


}

$ws_address = "localhost:8000";

$logger = new Logger();
$event_loop = Factory::create();

// Something that receives new data from an external system and keeps track of
// subscriptions. Any new data is sent to the subscriptions that have asked for
// the data. 
$subscription_handler = new RedisEventQueue($event_loop);

// Set up the Websocket server. It requires an event loop to handle some
// timeouts that are part of the GraphQL WS Protocol. 
$subscription_server = new GraphQLWsSubscriptionServer($logger, $event_loop);
$subscription_server->addEventHandler($subscription_handler);

// Set up our actual stack that handles HTTP and WebSockets. The server above is
// only the protocol handler itself.
$ws_app = new HttpServer(new WsServer($subscription_server));
$ws_socket = new Reactor($ws_address, $this->eventLoop); 
new IoServer($ws_app, $ws_socket, $this->eventLoop);

$logger->info(
  "Listening for WebSocket connections on ws://{address}",
  ["address" => $ws_address]
);

// Kick-off the React event loop to start our server.
$event_loop->run();
```

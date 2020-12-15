<?php

namespace GraphQLWs;

use GraphQL\Language\AST\DocumentNode;
use Ratchet\WebSocket\WsConnection;

/**
 * A GraphQL WebSocket Subscriber interface.
 *
 * Should be implemented by GraphQL Subscription resolvers.
 */
interface GraphQLWsSubscriberInterface extends GraphQLWsEventHandlerInterface {

  /**
   * Event handler for a new subscription.
   *
   * This is called by the GraphQL WebSocket Subscription Server when an
   * authenticated client opens a new subscription.
   *
   * @param string $subscription_id
   *   A unique identifier for this subscription provided by the client.
   * @param \Ratchet\WebSocket\WsConnection $client
   *   The WebSocket connection that requested the subscription. This can be
   *   stored to send data that the subscription requested.
   * @param \GraphQL\Language\AST\DocumentNode $document
   *   The GraphQL document for this subscription.
   * @param string|null $operationName
   *   The operation name if provided by the GraphQL client.
   * @param array|null $variables
   *   The variables provided by the client for the query, if any.
   */
  public function onSubscribe(string $subscription_id, WsConnection $client, DocumentNode $document, ?string $operationName = NULL, ?array $variables = NULL) : void;

  /**
   * Event handler for closing subscriptions.
   *
   * This is called by the GraphQL WebSocket Subscription Server when a client
   * indicates a subscription is completed or the server has terminated the
   * connection.
   *
   * @param string $subscription_id
   *   The id of the subscription that has been completed.
   */
  public function onComplete(string $subscription_id) : void;

}

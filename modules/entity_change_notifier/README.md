# Entity Change Notifier

Sends Notifications to various channels upon entity updates.

## Supported Destinations

* Any queue supported by the Drupal Queue API.
* A logging channel (for test purposes only).

## Notification API

Each destination should send notifications in the following format:

```json
{
  "action": "insert",
  "uri": "https://example.com/jsonapi/node/article/1ad7e005-2fad-4c65-b64e-6bb68c669524",
  "entity_id": 123,
  "entity_uuid": "1ad7e005-2fad-4c65-b64e-6bb68c669524",
  "entity_type": "node",
  "bundle": "article"
}
```

See [notification.json](notification.json) for a JSON schema version of this.

`action` is one of `insert`, `update`, or `delete`, mapping to the Drupal entity
hooks of the same name. The URI and specific format may change depending on
your site configuration.

Always fetch the URI to retrieve the most up-to-date version of the content.
Content may have been edited or deleted in the time between a notification and
when your application processes it. In other words, if the action is "save",
your application should handle that the content may have been edited or deleted
entirely.

For examples of how to query entities and walk relationships, see the
[JSON API](https://www.drupal.org/docs/8/modules/json-api/fetching-resources-get)
documentation. Remember that all requests must contain
`Accept: application/vnd.api+json`. Be sure to check the list of
[JSON API Implementations](http://jsonapi.org/implementations/), as there may
already be a library for your language or framework.

Notifications may be delayed or sent out of order especially if they are sent to
a remote server. Even if your destination preserves order (like a queue),
network issues may change the order notifications are sent in. Likewise, Drupal
editorial users may save or edit content many times before your application can
handle the notification.

## RabbitMQ Setup

This module supports sending notifications to any system that works with
Drupal's queue API. For example, to send notifications to
[RabbitMQ](https://www.drupal.org/project/rabbitmq):

```bash
# In your project root:
$ composer require drupal/rabbitmq
```

Edit `settings.php` to contain:

```php
// See the README.md in the rabbitmq module for more details.
$settings['rabbitmq_credentials'] = [
  'host' => 'localhost',
  'port' => 5672,
  'username' => 'guest',
  'password' => 'guest',
  'vhost' => '/'
];

// Tell Drupal to use RabbitMQ for this specific queue only.
$settings['queue_service_{QUEUE_NAME}'] = 'queue.rabbitmq';
$settings['queue_reliable_service_{QUEUE_NAME}'] = 'queue.rabbitmq';
```

## Comparisons to Other Modules

There are a few modules that have some overlap with Entity Change Notifier.
Here's why we ended up starting something new.

### Entity Pilot

[Entity Pilot](https://www.drupal.org/project/entity_pilot) is not just a
Drupal module but a SaaS solution. It's focus is on content federation between
multiple Drupal sites, and not distribution to non-Drupal platforms. Imports
into destinations are also driven by editorial, and not by an automated process.

### Entity Share

[Entity Share](https://www.drupal.org/project/entity_share) uses
[JSON API](https://www.drupal.org/project/jsonapi), just like what Entity
Change Notifier supports. Like Entity Pilot, it aims to share content to
other Drupal sites, but is not dependent on a SaaS service. However, it only
supports polling for entity updates, instead of pushing them out. Combined with
the focus on Drupal clients, we decided to write something new.

### Acquia Content Hub

[Acquia Content Hub](https://www.drupal.org/project/acquia_contenthub) is a
SaaS offering for ingesting content into Drupal. It's a very expensive
offering, and was overkill for our needs.

### What should I use?

If you need to share content to other Drupal sites, and not anything else, we
suggest investigating Entity Pilot or Entity Share first. If you only need to
send content to non-Drupal consumers, then Entity Change Notifier is a good
solution. If you need to do both, consider using more than one module, or
helping us develop an integration with one or both of the above modules.

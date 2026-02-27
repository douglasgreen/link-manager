<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../vendor/autoload.php';

use DouglasGreen\LinkManager\AppContainer;
use DouglasGreen\LinkManager\Controller\LinkController;

try {
    // Get application container (singleton)
    $app = AppContainer::getInstance();

    // Instantiate controller
    $controller = new LinkController($app);

    // Execute and send response
    $response = $controller->execute();
    $response->send();

} catch (Exception $e) {
    http_response_code(500);
    echo '<!DOCTYPE html><html><head><title>Error</title></head><body>';
    echo '<h1>Application Error</h1>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '</body></html>';
}

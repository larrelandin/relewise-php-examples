<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/env.php';

use Relewise\Tracker;
use Relewise\Models\TrackProductViewRequest;
use Relewise\Models\ProductView;
use Relewise\Models\Product;
use Relewise\Factory\UserFactory;

// Load environment variables
loadEnvVars(__DIR__ . '/../.env');
$apiKey = getenv('RELEWISE_API_KEY');
$datasetId = getenv('RELEWISE_DATASET_ID');
$serverUrl = getenv('RELEWISE_SERVER_URL');

// Create the tracker client
$tracker = new Tracker($datasetId, $apiKey, 5);
$tracker->serverUrl = $serverUrl;

// Set or get a temporary user ID from a cookie
$tempIdCookieName = 'relewise_temp_id';
if (isset($_COOKIE[$tempIdCookieName])) {
	$temporaryId = $_COOKIE[$tempIdCookieName];
} else {
	$temporaryId = bin2hex(random_bytes(16));
	setcookie($tempIdCookieName, $temporaryId, time() + 60*60*24*365, "/"); // 1 year
}

// Create a fake authenticated user with temporary ID
$user = UserFactory::byAuthenticatedId('user-123', $temporaryId);
$user->addToClassifications('Country', 'SV');

// Create a Product object for the visited product
$product = Product::create('67306c73-eb75-4b3b-bb27-b8f5123f1047');

// Create a ProductView object
$productView = ProductView::create($user, $product);

// Create the tracking request
$trackRequest = TrackProductViewRequest::create($productView);

// Send the tracking request
$result = $tracker->trackProductView($trackRequest);

echo '<h1>User product view tracked!</h1>';
echo '<pre>' . htmlspecialchars(print_r($result, true)) . '</pre>';
?>
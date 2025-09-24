<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Relewise\Searcher;
use Relewise\Models\ProductSearchRequest;
use Relewise\Models\SearchRequestCollection;
use Relewise\Models\ProductCategorySearchRequest;
use Relewise\Models\ProductCategorySearchSettings;
use Relewise\Models\SelectedProductCategoryPropertiesSettings;
use Relewise\Models\Language;
use Relewise\Models\Currency;
use Relewise\Factory\UserFactory;

// Load environment variables from .env if available
require_once __DIR__ . '/../src/env.php';
loadEnvVars(__DIR__ . '/../.env');

$apiKey = getenv('RELEWISE_API_KEY');
$datasetId = getenv('RELEWISE_DATASET_ID');
$serverUrl = getenv('RELEWISE_SERVER_URL');

// Create the searcher client
$searcher = new Searcher($datasetId, $apiKey, 5);
$searcher->serverUrl = $serverUrl;

// Prepare required arguments
$language = Language::create('en-gb'); // or your preferred language code
$currency = Currency::create('EUR'); // or your preferred currency code
$user = UserFactory::anonymous(); // or use another UserFactory method as needed
$displayedAtLocation = 'search-page';
$term = 'laptop';
$skip = 0;
$take = 5;


// Create settings to request displayName in results
$settings = \Relewise\Models\ProductSearchSettings::create();
$selectedProps = \Relewise\Models\SelectedProductPropertiesSettings::create();
$selectedProps->displayName = true;
$settings->selectedProductProperties = $selectedProps;


// Create the product search request with all required arguments
$request = ProductSearchRequest::create(
    $language,
    $currency,
    $user,
    $displayedAtLocation,
    $term,
    $skip,
    $take
);
$request->setSettings($settings);
$request->setFacets($facetQuery);

// Send the search request

// --- Product Category Search Request ---
$catSettings = ProductCategorySearchSettings::create();
$catSelectedProps = SelectedProductCategoryPropertiesSettings::create();
$catSelectedProps->setDisplayName(true)->setPaths(true)->setDataKeysFromArray(["Description", "ImagePath"]);
$catSettings->setSelectedCategoryProperties($catSelectedProps);

$catRequest = ProductCategorySearchRequest::create(
    $language,
    $currency,
    $user,
    $displayedAtLocation,
    $term,
    $skip,
    $take
);
$catRequest->setSettings($catSettings);

// --- Batch both requests if possible ---

// Batch both requests using SearchRequestCollection
$batch = SearchRequestCollection::create();
$batch->addToRequests($request);
$batch->addToRequests($catRequest);
$batchResponses = $searcher->batchsearch($batch);

// Parse the SearchResponseCollection by type
$productResponse = null;
$categoryResponse = null;


// Try both object and array access for 'Responses'

// Normalize the batch response to associative array and handle different key namings
$batchArray = json_decode(json_encode($batchResponses), true);
$responsesArr = [];
if (isset($batchArray['Responses']) && is_array($batchArray['Responses'])) {
    $responsesArr = $batchArray['Responses'];
} elseif (isset($batchArray['responses']) && is_array($batchArray['responses'])) {
    $responsesArr = $batchArray['responses'];
}

$productResponse = null;
$categoryResponse = null;
foreach ($responsesArr as $resp) {
    $type = $resp['$type'] ?? $resp['typeDefinition'] ?? $resp['type'] ?? '';
    if (stripos($type, 'ProductSearchResponse') !== false) {
        $productResponse = $resp;
    } elseif (stripos($type, 'ProductCategorySearchResponse') !== false) {
        $categoryResponse = $resp;
    }
}

// Fallback to positional responses if type info wasn't present
if (!$productResponse && isset($responsesArr[0])) {
    $productResponse = $responsesArr[0];
}
if (!$categoryResponse && isset($responsesArr[1])) {
    $categoryResponse = $responsesArr[1];
}






// Show total hit count at the top (try both 'Hits' and 'hits')
// Show total hit count at the top (support 'hits' and 'Hits')
$hits = $productResponse['hits'] ?? $productResponse['Hits'] ?? null;
if ($hits !== null) {
    echo '<h1>Batched search - Total products found for <i>' . htmlspecialchars($term) . '</i>: ' . htmlspecialchars($hits) . '</h1>';
}

// Output the products (support 'results' and 'Results')
$productResults = $productResponse['results'] ?? $productResponse['Results'] ?? [];
if (empty($productResults)) {
    echo '<p>No products found.</p>';
    if (empty($responsesArr)) {
        echo '<pre>No Responses found in batch response. Raw batch: ' . htmlspecialchars(json_encode($batchArray)) . '</pre>';
    } else {
        $types = array_map(function($r){ return $r['typeDefinition'] ?? $r['$type'] ?? '(no type)'; }, $responsesArr);
        echo '<pre>Received response types: ' . htmlspecialchars(json_encode($types)) . '</pre>';
        // echo '<pre>Batch array: ' . htmlspecialchars(json_encode($batchArray, JSON_PRETTY_PRINT)) . '</pre>';
    }
} else {
    foreach ($productResults as $product) {
        $displayName = $product['displayName'] ?? $product['DisplayName'] ?? '';
        $productId = $product['productId'] ?? $product['ProductId'] ?? '';
        echo '<div>';
        echo '<h3>' . htmlspecialchars($displayName) . '</h3>';
        echo '<p>ID: ' . htmlspecialchars($productId) . '</p>';
        echo '</div>';
    }
}

// Output the product categories from ProductCategorySearchResponse (try both 'Results' and 'results')
$catResults = $categoryResponse['results'] ?? $categoryResponse['Results'] ?? [];
if (!empty($catResults)) {
    echo '<h2>Search for Matching Product Categories</h2><ul>';
    foreach ($catResults as $cat) {
        $catName = $cat['displayName'] ?? $cat['DisplayName'] ?? '';
        $catId = $cat['categoryId'] ?? $cat['CategoryId'] ?? '';
        echo '<li>' . htmlspecialchars($catName) . ' (ID: ' . htmlspecialchars($catId) . ')</li>';
    }
    echo '</ul>';
} else {
    echo '<h2>No matching product categories found.</h2>';
}
?>
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Relewise\Searcher;
use Relewise\Models\ProductSearchRequest;
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


// Add facets for product categories, brand, and margin
$facetQuery = \Relewise\Models\ProductFacetQuery::create();
$categoryFacet = \Relewise\Models\CategoryFacet::create(
    \Relewise\Models\CategorySelectionStrategy::Ancestors
);
$brandFacet = \Relewise\Models\BrandFacet::create();
$marginFacet = \Relewise\Models\ProductDataStringValueFacet::create(
    \Relewise\Models\DataSelectionStrategy::Product,
    'Margin',
    null,
    null
);
$facetQuery->setItems($categoryFacet, $brandFacet, $marginFacet);

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
$response = $searcher->productSearch($request);



// Show total hit count at the top
if (isset($response->hits)) {
    echo '<h1>Total products found for <i>' .$term. '</i>: ' . htmlspecialchars($response->hits) . '</h1>';
}

// Output the category facet if available
if (isset($response->facets) && isset($response->facets->items)) {
    foreach ($response->facets->items as $facet) {
        if ($facet instanceof \Relewise\Models\CategoryFacetResult && isset($facet->available)) {
            echo '<h2>Product Categories</h2><ul>';
            foreach ($facet->available as $cat) {
                $catName = isset($cat->value->displayName) && $cat->value->displayName ? $cat->value->displayName : $cat->value->id;
                $catHits = isset($cat->hits) ? $cat->hits : 0;
                echo '<li>' . htmlspecialchars($catName). ' - ' . htmlspecialchars($catHits) . ' hits</li>';
            }
            echo '</ul>';
        }
    }

    // Output other facets (Brand, Margin, etc.)
    echo '<h2>Other Facets</h2>';
    foreach ($response->facets->items as $facet) {
        if ($facet instanceof \Relewise\Models\CategoryFacetResult) continue;
        if (isset($facet->available) && is_array($facet->available) && count($facet->available) > 0) {
            $facetType = (new \ReflectionClass($facet))->getShortName();
            echo '<h3>' . htmlspecialchars($facetType) . '</h3><ul>';
            foreach ($facet->available as $item) {
                $name = '';
                if (isset($item->value->displayName) && $item->value->displayName) {
                    $name = $item->value->displayName;
                } elseif (isset($item->value->id)) {
                    $name = $item->value->id;
                } elseif (isset($item->value)) {
                    $name = $item->value;
                }
                $hits = isset($item->hits) ? $item->hits : 0;
                echo '<li>' . htmlspecialchars($name) . ' - ' . htmlspecialchars($hits) . ' hits</li>';
            }
            echo '</ul>';
        }
    }
}

// Output the products
if (empty($response->results)) {
    echo '<p>No products found.</p>';
} else {
    foreach ($response->results as $product) {
        echo '<div>';
        echo '<h3>' . htmlspecialchars($product->displayName ?? $product->productId) . '</h3>';
        echo '<p>ID: ' . htmlspecialchars($product->productId) . '</p>';
        // Add more product fields as needed
        echo '</div>';
    }
}
?>
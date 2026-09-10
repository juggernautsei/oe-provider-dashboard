<?php

/**
 * Shared HTML head for Provider Dashboard pages.
 *
 * @package OpenEMR
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU GPL-3.0-only
 */

$ctx = provider_dashboard_context();
$assetBase = rtrim($ctx['moduleWebPath'], '/');
?>
<head>
    <title><?php echo xlt($title ?? 'Provider Dashboard'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700,800,900" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="<?php echo attr($assetBase . '/css/style.css'); ?>">
</head>

<?php get_header(); ?>

<div class="nab-checkout-wrapper">
    <div class="nab-checkout-card">

        <h2 class="nab-title">Secure Checkout</h2>

        <!-- Dynamic Processing Text -->
        <p id="nab-processing-text" class="nab-processing-text"></p>

        <!-- Loader -->
        <div id="nab-loader" class="nab-loader">
            <div class="nab-spinner"></div>
            <p>Please wait while we prepare your secure payment...</p>
        </div>

        <!-- Unified Checkout Container -->
        <div id="nab-card-container" class="nab-payment-container"></div>
        <div id="nab-card-container-embedded" class="nab-payment-container"></div>
    </div>
</div>

<?php get_footer(); ?>

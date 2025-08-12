<?php
/**
 * WHMCS Hooks – Secure Webhook Template
 *
 * This file demonstrates how to forward WHMCS client-related events
 * (create / update / delete) to an external system (e.g. n8n) via HTTP,
 * wrapping each payload in a signed JWT for tamper-evident delivery.
 *
 * HOW TO USE
 * 1. Copy this file to  /includes/hooks/  inside your WHMCS install.
 * 2. Replace all  REPLACE_WITH_WEBHOOK_URL_HERE  placeholders with the
 *    URL of your HTTP Webhook node (or similar) in n8n.
 * 3. Generate a long, random secret key (≥ 32 chars) and configure it
 *    securely (environment variable, configuration file outside web-root,
 *    or encrypted storage).  Never commit the real key to GitHub.
 * 4. Make sure the very same key is used in n8n to verify the signature.
 *
 * License: MIT – see README.md
 * Author : Little.Cloud – James Royal <helpdesk@little.cloud>
 * GitHub : https://github.com/littlecloud/whmcs-jwt-webhooks-template
 */

// Ensure this script can't be accessed directly
if (!defined('WHMCS')) {
    die('You cannot access this file directly.');
}

/**
 * Shared secret key used to sign the JWT.
 *
 * IMPORTANT:
 * – Replace the dummy value below with a strong, random string.
 * – Store it safely (env var, secrets manager, etc.).
 * – The same key **must** be configured in your n8n workflow for
 *   verification; otherwise the request will be rejected.
 */
const JWT_SECRET_KEY = 'CHANGE_ME_SUPER_SECRET_KEY';

/**
 * Creates and signs a JWT for a given payload.
 *
 * @param array $payload The data to include in the token.
 * @return string The signed JWT.
 */
function createJwt(array $payload) {
    // Encode the header and payload.
    $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
    $payloadJson = json_encode($payload);
    
    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payloadJson));

    // Create the signature.
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET_KEY, true);
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

    // Combine all parts to create the final token.
    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

/**
 * Sends a webhook notification when a new client is added.
 *
 * This function is registered to the 'ClientAdd' hook point. It
 * receives an array of variables related to the new client,
 * creates a JSON payload, and sends it to the specified
 * webhook URL using cURL.
 *
 * @param array $vars An array of data from the WHMCS hook.
 */
function send_client_add_webhook($vars) {
    // The webhook URL provided by the user.
    $webhookUrl = 'REPLACE_WITH_WEBHOOK_URL_HERE';

    // TODO: Replace with your n8n Webhook URL (HTTPS recommended).

    // Verify that the required variables exist before proceeding.
    if (!isset($vars['client_id'])) {
        logActivity('ClientAdd hook failed: Missing client ID in variables.', 0);
        return;
    }

    // Extract key client details from the hook variables
    // and build the payload for the webhook.
    $payload = [
        'firstname' => $vars['firstname'] ?? '',
        'lastname'  => $vars['lastname'] ?? '',
        'email'     => $vars['email'] ?? '',
        'clientId'  => $vars['client_id'],
    ];

    // Create a JWT for secure communication.
    $jwt = createJwt($payload);

    // Initialize cURL session to send the data.
    $ch = curl_init();

    try {
        // Set cURL options for the POST request.
        curl_setopt($ch, CURLOPT_URL, $webhookUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        // Add a timeout to prevent the script from hanging indefinitely.
        // A 10-second timeout is a good starting point.
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        // Set the Content-Type header to 'application/json'
        // and include the JWT in the Authorization header.
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $jwt
        ]);

        // Execute the cURL request.
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Check for cURL errors.
        if (curl_errno($ch)) {
            $errorMessage = 'Webhook cURL Error: ' . curl_error($ch);
            logActivity($errorMessage, $vars['client_id']);
        } else {
            // Log the webhook response for successful requests.
            logActivity("Webhook sent for new client ID {$vars['client_id']}. Response Status: {$httpCode}, Body: " . substr($response, 0, 200), $vars['client_id']);
        }

    } catch (Exception $e) {
        // Catch any other general exceptions and log them.
        logActivity('Webhook Exception: ' . $e->getMessage(), $vars['client_id']);
    } finally {
        // Close the cURL session in all cases.
        curl_close($ch);
    }
}

/**
 * Sends a webhook notification when a client's details are updated.
 *
 * This function is registered to the 'ClientEdit' hook point. It
 * receives an array of variables related to the client update,
 * creates a JSON payload, and sends it to the specified
 * webhook URL.
 *
 * @param array $vars An array of data from the WHMCS hook.
 */
function send_client_update_webhook($vars) {
    // The webhook URL for client updates.
    $webhookUrl = 'REPLACE_WITH_WEBHOOK_URL_HERE';

    // TODO: Replace with your n8n Webhook URL.

    if (!isset($vars['userid'])) {
        logActivity('ClientEdit hook failed: Missing client ID in variables.', 0);
        return;
    }

    // Build the payload with the new client data.
    $payload = [
        'clientId'  => $vars['userid'],
        'firstname' => $vars['firstname'] ?? '',
        'lastname'  => $vars['lastname'] ?? '',
        'email'     => $vars['email'] ?? '',
        'action'    => 'updated',
    ];
    
    // Create a JWT for secure communication.
    $jwt = createJwt($payload);

    // Initialize cURL session to send the data.
    $ch = curl_init();
    
    try {
        curl_setopt($ch, CURLOPT_URL, $webhookUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $jwt
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $errorMessage = 'Webhook cURL Error: ' . curl_error($ch);
            logActivity($errorMessage, $vars['userid']);
        } else {
            logActivity("Webhook sent for client update, ID {$vars['userid']}. Response Status: {$httpCode}, Body: " . substr($response, 0, 200), $vars['userid']);
        }

    } catch (Exception $e) {
        logActivity('Webhook Exception: ' . $e->getMessage(), $vars['userid']);
    } finally {
        curl_close($ch);
    }
}

/**
 * Sends a webhook notification when a client is deleted.
 *
 * This function is registered to the 'ClientDelete' hook point. It
 * receives an array of variables related to the client deletion,
 * creates a JSON payload, and sends it to the specified
 * webhook URL.
 *
 * @param array $vars An array of data from the WHMCS hook.
 */
function send_client_delete_webhook($vars) {
    // The webhook URL for client deletions.
    $webhookUrl = 'REPLACE_WITH_WEBHOOK_URL_HERE';

    // TODO: Replace with your n8n Webhook URL.

    if (!isset($vars['userid'])) {
        logActivity('ClientDelete hook failed: Missing client ID in variables.', 0);
        return;
    }

    // Build a simple payload for deletion.
    $payload = [
        'clientId'  => $vars['userid'],
        'action'    => 'deleted',
    ];

    // Create a JWT for secure communication.
    $jwt = createJwt($payload);

    // Convert the payload array to a JSON string.
    $jsonData = json_encode($payload);

    // Initialize cURL session to send the data.
    $ch = curl_init();

    try {
        curl_setopt($ch, CURLOPT_URL, $webhookUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $jwt
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $errorMessage = 'Webhook cURL Error: ' . curl_error($ch);
            logActivity($errorMessage, $vars['userid']);
        } else {
            logActivity("Webhook sent for client deletion, ID {$vars['userid']}. Response Status: {$httpCode}, Body: " . substr($response, 0, 200), $vars['userid']);
        }

    } catch (Exception $e) {
        logActivity('Webhook Exception: ' . $e->getMessage(), $vars['userid']);
    } finally {
        curl_close($ch);
    }
}

// Register our functions to their respective hook points.
add_hook('ClientAdd', 1, 'send_client_add_webhook');
add_hook('ClientEdit', 1, 'send_client_update_webhook');
add_hook('ClientDelete', 1, 'send_client_delete_webhook');

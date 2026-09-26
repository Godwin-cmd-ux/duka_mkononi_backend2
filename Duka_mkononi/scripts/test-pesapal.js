/**
 * =============================================
 * 🔍 PESAPAL PAYMENT CONFIGURATION TEST
 * =============================================
 * 
 * This script tests your PesaPal sandbox API integration.
 * Run: node scripts/test-pesapal.js
 * 
 * Set these environment variables before running:
 *   PESAPAL_CONSUMER_KEY    - Your PesaPal consumer key (sandbox)
 *   PESAPAL_CONSUMER_SECRET - Your PesaPal consumer secret (sandbox)
 *   PESAPAL_NOTIFICATION_ID - Your IPN notification ID
 *   PESAPAL_ENV             - Set to 'live' for production, omit for sandbox
 * 
 * Get sandbox credentials: https://www.pesapal.com/merchant/dashboard
 */

const PESAPAL_CONFIG = {
    baseUrl: process.env.PESAPAL_ENV === 'live'
        ? 'https://pay.pesapal.com/v3'
        : 'https://cybqa.pesapal.com/pesapalv3',
    consumerKey: process.env.PESAPAL_CONSUMER_KEY || '',
    consumerSecret: process.env.PESAPAL_CONSUMER_SECRET || '',
    notificationId: process.env.PESAPAL_NOTIFICATION_ID || '',
    callbackUrl: process.env.PESAPAL_CALLBACK_URL || 'http://localhost:3000/api/payments/pesapal-callback',
    ipnUrl: process.env.PESAPAL_IPN_URL || ''
};

// ANSI Colors for output
const colors = {
    reset: '\x1b[0m',
    red: '\x1b[31m',
    green: '\x1b[32m',
    yellow: '\x1b[33m',
    blue: '\x1b[34m',
    magenta: '\x1b[35m',
    cyan: '\x1b[36m',
    gray: '\x1b[90m',
    bold: '\x1b[1m',
    dim: '\x1b[2m'
};

function log(msg, color = colors.reset, icon = '') {
    console.log(`${icon ? icon + ' ' : ''}${color}${msg}${colors.reset}`);
}

function logSection(title) {
    console.log(`\n${colors.bold}${colors.cyan}═══ ${title} ═══${colors.reset}\n`);
}

function logPass(msg) {
    log(`✅ ${msg}`, colors.green);
}

function logFail(msg, detail = '') {
    log(`❌ ${msg}`, colors.red);
    if (detail) log(`   ${detail}`, colors.gray);
}

function logWarn(msg, detail = '') {
    log(`⚠️  ${msg}`, colors.yellow);
    if (detail) log(`   ${detail}`, colors.gray);
}

function logInfo(msg) {
    log(`ℹ️  ${msg}`, colors.blue);
}

// =============================================
// STEP 1: Validate Configuration
// =============================================
function validateConfig() {
    logSection('STEP 1: Configuration Validation');

    let allPass = true;
    let score = 0;
    const total = 7;

    // 1. Check baseUrl
    if (PESAPAL_CONFIG.baseUrl) {
        const isLive = PESAPAL_CONFIG.baseUrl.includes('pay.pesapal.com');
        logPass(`Base URL: ${PESAPAL_CONFIG.baseUrl} ${isLive ? '(🔴 LIVE MODE!)' : '(🟢 Sandbox)'}`);
        score++;
    } else {
        logFail('Base URL is empty');
        allPass = false;
    }

    // 2. Check consumerKey
    if (PESAPAL_CONFIG.consumerKey) {
        logPass(`Consumer Key: ${PESAPAL_CONFIG.consumerKey.substring(0, 8)}...${PESAPAL_CONFIG.consumerKey.slice(-4)}`);
        score++;
    } else {
        logFail('Consumer Key is empty — set PESAPAL_CONSUMER_KEY env var');
        allPass = false;
    }

    // 3. Check consumerSecret
    if (PESAPAL_CONFIG.consumerSecret) {
        logPass(`Consumer Secret: ${'*'.repeat(PESAPAL_CONFIG.consumerSecret.length)}`);
        score++;
    } else {
        logFail('Consumer Secret is empty — set PESAPAL_CONSUMER_SECRET env var');
        allPass = false;
    }

    // 4. Check notificationId
    if (PESAPAL_CONFIG.notificationId) {
        logPass(`Notification ID: ${PESAPAL_CONFIG.notificationId.substring(0, 8)}...`);
        score++;
    } else {
        logWarn('Notification ID is empty — set PESAPAL_NOTIFICATION_ID env var', 
            'Required for IPN (Instant Payment Notification) to work');
    }

    // 5. Check callbackUrl
    if (PESAPAL_CONFIG.callbackUrl && !PESAPAL_CONFIG.callbackUrl.includes('your-backend')) {
        logPass(`Callback URL: ${PESAPAL_CONFIG.callbackUrl}`);
        score++;
    } else {
        logWarn('Callback URL is a placeholder', 
            `Current: ${PESAPAL_CONFIG.callbackUrl}\n   Update to your actual backend URL`);
    }

    // 6. Check ipnUrl
    if (PESAPAL_CONFIG.ipnUrl && !PESAPAL_CONFIG.ipnUrl.includes('your-backend-url')) {
        logPass(`IPN URL: ${PESAPAL_CONFIG.ipnUrl}`);
        score++;
    } else {
        logWarn('IPN URL is empty or a placeholder',
            'Set PESAPAL_IPN_URL to your public URL + /api/payments/pesapal-ipn');
    }

    // 7. Validate credential length
    if (PESAPAL_CONFIG.consumerKey && PESAPAL_CONFIG.consumerSecret) {
        if (PESAPAL_CONFIG.consumerKey.length > 10 && PESAPAL_CONFIG.consumerSecret.length > 10) {
            logPass('Credentials look like valid PesaPal API keys');
            score++;
        } else {
            logWarn('Credentials seem unusually short — double check they are correct',
                `Key length: ${PESAPAL_CONFIG.consumerKey.length}, Secret length: ${PESAPAL_CONFIG.consumerSecret.length}`);
        }
    }

    console.log(`\n📊 Configuration Score: ${score}/${total}`);
    if (score === total) {
        logPass('All configuration checks passed!');
    } else if (score >= 4) {
        logWarn(`${total - score} configuration item(s) need attention`);
    } else {
        logFail('Multiple configuration items need fixing');
        allPass = false;
    }

    return allPass;
}

// =============================================
// STEP 2: Test API Connectivity
// =============================================
async function testApiConnectivity() {
    const envMode = PESAPAL_CONFIG.baseUrl.includes('pay.pesapal.com') ? 'LIVE' : 'SANDBOX';
    logSection(`STEP 2: API Connectivity Test (${envMode})`);

    if (!PESAPAL_CONFIG.consumerKey || !PESAPAL_CONFIG.consumerSecret) {
        logFail('Skipping API test — missing consumer key or secret');
        return false;
    }

    try {
        logInfo(`Connecting to: ${PESAPAL_CONFIG.baseUrl}/api/Auth/RequestToken`);
        
        console.log(`\n${colors.dim}Request Details:${colors.reset}`);
        console.log(`${colors.dim}  Method: POST${colors.reset}`);
        console.log(`${colors.dim}  URL: ${PESAPAL_CONFIG.baseUrl}/api/Auth/RequestToken${colors.reset}`);
        console.log(`${colors.dim}  Auth: Credentials sent in body (no Authorization header)${colors.reset}`);
        
        const startTime = Date.now();
        
        const response = await fetch(`${PESAPAL_CONFIG.baseUrl}/api/Auth/RequestToken`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                consumer_key: PESAPAL_CONFIG.consumerKey,
                consumer_secret: PESAPAL_CONFIG.consumerSecret
            })
        });
        
        const elapsed = Date.now() - startTime;
        const data = await response.json();
        
        console.log(`\n${colors.dim}Response (${elapsed}ms):${colors.reset}`);
        console.log(`${colors.dim}  Status: ${response.status}${colors.reset}`);
        console.log(`${colors.dim}  Body: ${JSON.stringify(data, null, 4)}${colors.reset}`);

        if (response.ok && data.token) {
            logPass(`Access token obtained! (${elapsed}ms)`);
            logInfo(`Token: ${data.token.substring(0, 30)}...`);
            return data.token;
        } else {
            logFail(`API Error: ${data.error?.message || data.error?.code || JSON.stringify(data)}`,
                `Response time: ${elapsed}ms`);
            
            // Helpful error diagnostics
            if (data.error?.code === 'invalid_consumer_key_or_secret_provided') {
                logWarn('These credentials are not valid for this environment.');
                logInfo('If these are LIVE keys, set PESAPAL_ENV=live and try again.');
                logInfo('If these are SANDBOX keys, set PESAPAL_ENV=sandbox and try again.');
            }
            
            return null;
        }

    } catch (error) {
        logFail(`Network/Connection error: ${error.message}`);
        
        if (error.code === 'ENOTFOUND' || error.code === 'ECONNREFUSED') {
            logWarn('Cannot reach PesaPal servers. Check your internet connection or the API URL.');
        } else if (error.code === 'ETIMEDOUT') {
            logWarn('Connection timed out. PesaPal sandbox might be down or slow.');
        }
        
        return null;
    }
}

// =============================================
// STEP 3: Test Order Submission (with token)
// =============================================
async function testOrderSubmission(token) {
    const envLabel = PESAPAL_CONFIG.baseUrl.includes('pay.pesapal.com') ? 'LIVE' : 'SANDBOX';
    logSection(`STEP 3: Order Submission Test (${envLabel})`);

    if (!token) {
        logFail('Skipping order test — no access token available');
        return { success: false };
    }

    const testOrder = {
        id: `TEST-${Date.now()}`,
        currency: "TZS",
        amount: "1000.00",
        description: "Test payment from DukaMkononi diagnostic script",
        callback_url: PESAPAL_CONFIG.callbackUrl,
        notification_id: PESAPAL_CONFIG.notificationId,
        billing_address: {
            email_address: "test@dukamkononi.com",
            phone_number: "255712345678",
            country_code: "TZ",
            first_name: "Test",
            middle_name: "",
            last_name: "User",
            line_1: "Sample Address",
            line_2: "",
            city: "Dar es Salaam",
            state: "Dar es Salaam",
            postal_code: "",
            zip_code: ""
        }
    };

    try {
        logInfo('Submitting test order (TZS 1,000)...');
        
        const startTime = Date.now();
        const response = await fetch(`${PESAPAL_CONFIG.baseUrl}/api/Transactions/SubmitOrderRequest`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify(testOrder)
        });
        
        const elapsed = Date.now() - startTime;
        const data = await response.json();

        console.log(`\n${colors.dim}Response (${elapsed}ms):${colors.reset}`);
        console.log(`${colors.dim}  Status: ${response.status}${colors.reset}`);
        console.log(`${colors.dim}  Body: ${JSON.stringify(data, null, 4)}${colors.reset}`);

        if (response.ok && data.redirect_url) {
            logPass(`✅ Order submitted successfully! (${elapsed}ms)`);
            logInfo(`Order Tracking ID: ${data.order_tracking_id || 'N/A'}`);
            logInfo(`Merchant Reference: ${data.merchant_reference || 'N/A'}`);
            logInfo(`Redirect URL: ${data.redirect_url}`);
            
            console.log(`\n${colors.bold}${colors.green}🔗 You can open this URL in your browser to test the payment flow:${colors.reset}`);
            console.log(`${colors.cyan}${data.redirect_url}${colors.reset}\n`);

            return { 
                success: true, 
                redirect_url: data.redirect_url,
                order_tracking_id: data.order_tracking_id 
            };
        } else {
            logFail(`Order submission failed (${response.status}): ${data.error || JSON.stringify(data)}`);
            
            if (data.error?.includes('notification_id') || data.error?.includes('ipn')) {
                logWarn('Notification ID issue. Register an IPN URL in your PesaPal dashboard first.');
            }
            
            return { success: false, error: data };
        }

    } catch (error) {
        logFail(`Order submission error: ${error.message}`);
        return { success: false, error: error.message };
    }
}

// =============================================
// STEP 4: Check Transaction Status
// =============================================
async function testTransactionStatus(token, orderTrackingId) {
    logSection('STEP 4: Transaction Status Check');

    if (!token) {
        logFail('Skipping — no access token');
        return;
    }
    if (!orderTrackingId) {
        logWarn('Skipping — no order tracking ID from previous step');
        return;
    }

    try {
        logInfo(`Checking status for: ${orderTrackingId}`);
        
        const startTime = Date.now();
        const response = await fetch(
            `${PESAPAL_CONFIG.baseUrl}/api/Transactions/GetTransactionStatus?orderTrackingId=${orderTrackingId}`,
            {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${token}`
                }
            }
        );

        const elapsed = Date.now() - startTime;
        const data = await response.json();

        console.log(`\n${colors.dim}Response (${elapsed}ms):${colors.reset}`);
        console.log(`${colors.dim}  Status: ${response.status}${colors.reset}`);
        console.log(`${colors.dim}  Body: ${JSON.stringify(data, null, 4)}${colors.reset}`);

        if (response.ok) {
            logPass(`Status check succeeded (${elapsed}ms)`);
            logInfo(`Payment Status: ${data.status_code || data.status || 'N/A'}`);
        } else {
            logFail(`Status check failed (${response.status})`);
        }

    } catch (error) {
        logFail(`Status check error: ${error.message}`);
    }
}

// =============================================
// STEP 5: Check IPN URL Registration
// =============================================
async function testIpnRegistration() {
    logSection('STEP 5: IPN URL Registration Check');

    if (!PESAPAL_CONFIG.consumerKey || !PESAPAL_CONFIG.consumerSecret) {
        logFail('Skipping — missing credentials');
        return;
    }

    logInfo('To register an IPN URL, go to your PesaPal dashboard:');
    console.log(`${colors.cyan}   https://www.pesapal.com/merchant/dashboard${colors.reset}`);
    console.log(`   Then navigate to: IPN Settings → Register URL`);
    console.log(`\n   Your IPN URL should be:`);
    console.log(`${colors.green}   ${PESAPAL_CONFIG.ipnUrl || 'https://your-domain.com/api/payments/pesapal-ipn'}${colors.reset}`);
    console.log(`\n   After registering, copy the Notification ID and set it as:`);
    console.log(`${colors.green}   PESAPAL_NOTIFICATION_ID=your_notification_id_here${colors.reset}`);
}

// =============================================
// 🔧 HELPER: What the fix should be
// =============================================
function showFixGuide() {
    logSection('🔧 REQUIRED FIXES');

    console.log(`${colors.bold}1. Create .env file in project root:${colors.reset}`);
    console.log(`${colors.green}`);
    console.log(`   # PesaPal Configuration`);
    console.log(`   PESAPAL_CONSUMER_KEY=your_consumer_key_here`);
    console.log(`   PESAPAL_CONSUMER_SECRET=your_consumer_secret_here`);
    console.log(`   PESAPAL_NOTIFICATION_ID=your_ipn_notification_id`);
    console.log(`   PESAPAL_CALLBACK_URL=https://www.dukamkononi.com/api/payments/pesapal-callback`);
    console.log(`   PESAPAL_IPN_URL=https://www.dukamkononi.com/api/payments/pesapal-ipn`);
    console.log(`   PESAPAL_ENV=sandbox  # or 'live' for production`);
    console.log(`${colors.reset}`);

    console.log(`${colors.bold}2. Auth header fix applied to getPesapalAccessToken():${colors.reset}`);
    console.log(`${colors.green}   ✅ Removed unnecessary Authorization header entirely${colors.reset}`);
    console.log(`   Credentials are now sent only in the request body (JSON).`);

    console.log(`${colors.bold}3. Fix callback URL in PESAPAL_CONFIG:${colors.reset}`);
    console.log(`${colors.yellow}   Current: http://localhost:3000/payment-callback${colors.reset}`);
    console.log(`${colors.green}   Correct: http://localhost:3000/api/payments/pesapal-callback${colors.reset}`);
    console.log(`   (Or use your production domain URL for external access)`);

    console.log(`${colors.bold}4. Get PesaPal Sandbox Credentials:${colors.reset}`);
    console.log(`   - Go to: ${colors.cyan}https://www.pesapal.com/merchant/dashboard${colors.reset}`);
    console.log(`   - Register/Login → API Keys → Generate Sandbox Keys`);
    console.log(`   - Then: IPN Settings → Register URL → Get Notification ID`);
}

// =============================================
// 🚀 MAIN
// =============================================
async function main() {
    console.log(`\n${colors.bold}${colors.magenta}╔══════════════════════════════════════════════╗${colors.reset}`);
    console.log(`${colors.bold}${colors.magenta}║   🔍 PESAPAL PAYMENT CONFIGURATION TEST       ║${colors.reset}`);
    console.log(`${colors.bold}${colors.magenta}║      DukaMkononi - Diagnostic Tool            ║${colors.reset}`);
    console.log(`${colors.bold}${colors.magenta}╚══════════════════════════════════════════════╝${colors.reset}`);
    console.log(`\n${colors.dim}Date: ${new Date().toISOString()}${colors.reset}`);
    console.log(`${colors.dim}Mode: ${PESAPAL_CONFIG.baseUrl.includes('pay.pesapal.com') ? '🔴 LIVE' : '🟢 SANDBOX'}
${colors.reset}\n`);

    // Run tests
    const configOk = validateConfig();
    
    if (!PESAPAL_CONFIG.consumerKey || !PESAPAL_CONFIG.consumerSecret) {
        logSection('STEP 2 & 3: SKIPPED');
        logWarn('Set PESAPAL_CONSUMER_KEY and PESAPAL_CONSUMER_SECRET to run API tests');
        showFixGuide();
        console.log(`\n${colors.bold}${colors.red}═══ TEST INCONCLUSIVE — Missing credentials ═══${colors.reset}\n`);
        process.exit(0);
    }

    const token = await testApiConnectivity();
    
    let orderResult = { success: false };
    if (token) {
        orderResult = await testOrderSubmission(token);
    }

    if (orderResult.success) {
        await testTransactionStatus(token, orderResult.order_tracking_id);
    }

    await testIpnRegistration();
    showFixGuide();

    // Summary
    console.log(`\n${colors.bold}${colors.magenta}═══════════════════════════════════════════${colors.reset}`);
    if (token && orderResult.success) {
        logPass('API connection and order submission working! 🎉');
        console.log(`\n${colors.green}Your PesaPal integration is functional!`);
        console.log(`Fix the minor issues above and you're ready to go.${colors.reset}`);
    } else if (token) {
        logWarn('API connection works but order submission needs attention');
    } else {
        logFail('API connection failed. Check your credentials and try again.');
    }
    console.log(`${colors.bold}${colors.magenta}═══════════════════════════════════════════${colors.reset}\n`);
}

main().catch(error => {
    console.error(`${colors.red}Fatal error:${colors.reset}`, error.message);
    process.exit(1);
});

/**
 * API Base URL — the Laravel backend that now serves Duka Mkononi.
 *
 * The app previously talked to the old Node.js backend (Render/ngrok). All
 * requests now go through the Laravel API at https://www.dukamkononi.com
 * (routes/api.php), which is the authoritative implementation.
 *
 * For local development against `php artisan serve`, temporarily override
 * this value (e.g. Android emulator: http://10.0.2.2:8000).
 */
export const API_BASE_URL: string = 'https://www.dukamkononi.com';

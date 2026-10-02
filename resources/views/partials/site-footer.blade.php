{{--
    Public website footer. Reuses the platform's verified contact details only
    (info@dukamkononi.com and the officially configured phone number). No
    invented phone or WhatsApp number.
--}}
<footer class="dm-footer">
    <div class="dm-container">
        <div class="dm-footer-grid">
            <div>
                <div class="dm-brand" style="color:#fff;margin-bottom:12px">
                    <span class="dm-brand-mark" aria-hidden="true">DM</span>
                    <span>Duka<span style="color:var(--dm-accent)">Mkononi</span></span>
                </div>
                <p style="font-size:14px" data-i18n="site_common.footer_about">Connecting customers with businesses and products across Tanzania.</p>
            </div>
            <div>
                <h4 data-i18n="site_common.footer_explore">Explore</h4>
                <ul>
                    <li><a href="/shop" data-i18n="site_nav.shop">Shop</a></li>
                    <li><a href="/businesses" data-i18n="site_nav.businesses">Businesses</a></li>
                    <li><a href="/advertisements" data-i18n="site_nav.advertisements">Advertisements</a></li>
                    <li><a href="/track-orders" data-i18n="site_nav.track_orders">Track Orders</a></li>
                </ul>
            </div>
            <div>
                <h4 data-i18n="site_nav.contact">Contact</h4>
                <ul>
                    <li><a href="mailto:info@dukamkononi.com">info@dukamkononi.com</a></li>
                    <li><a href="tel:0757071967">0757071967</a></li>
                    <li><a href="https://wa.me/255757071967" target="_blank" rel="noopener">WhatsApp</a></li>
                </ul>
            </div>
        </div>
        <div class="dm-footer-bottom">
            <span>&copy; {{ date('Y') }} Duka Mkononi.</span>
            <span data-i18n="site_common.footer_rights">All rights reserved.</span>
        </div>
    </div>
</footer>

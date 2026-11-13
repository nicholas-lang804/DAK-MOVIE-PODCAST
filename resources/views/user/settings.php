<?php
/**
 * User Settings View
 */
?>

<section class="user-settings">
    <div class="section-header">
        <h1>Settings</h1>
        <p>Manage your preferences</p>
    </div>

    <?php if (isset($_SESSION['settings_success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['settings_success']); ?></div>
        <?php unset($_SESSION['settings_success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['settings_error'])): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['settings_error']); ?></div>
        <?php unset($_SESSION['settings_error']); ?>
    <?php endif; ?>

    <div class="form-container">
        <form method="POST" action="/TMZ%20PODCAST/user/settings.php" class="admin-form">
            <?php echo CSRF::getField(); ?>
            
            <div class="form-group">
                <label for="preferred_currency">Display and payment currency</label>
                <select id="preferred_currency" name="preferred_currency">
                    <?php foreach (['DZD'=>'Algerian Dinar','AOA'=>'Angolan Kwanza','BWP'=>'Botswana Pula','BIF'=>'Burundian Franc','CVE'=>'Cape Verde Escudo','XAF'=>'Central African CFA Franc','XOF'=>'West African CFA Franc','KMF'=>'Comorian Franc','CDF'=>'Congolese Franc','DJF'=>'Djiboutian Franc','EGP'=>'Egyptian Pound','ERN'=>'Eritrean Nakfa','ETB'=>'Ethiopian Birr','SZL'=>'Eswatini Lilangeni','GMD'=>'Gambian Dalasi','GHS'=>'Ghanaian Cedi','GNF'=>'Guinean Franc','KES'=>'Kenyan Shilling','LSL'=>'Lesotho Loti','LRD'=>'Liberian Dollar','LYD'=>'Libyan Dinar','MGA'=>'Malagasy Ariary','MWK'=>'Malawian Kwacha','MRU'=>'Mauritanian Ouguiya','MUR'=>'Mauritian Rupee','MAD'=>'Moroccan Dirham','MZN'=>'Mozambican Metical','NAD'=>'Namibian Dollar','NGN'=>'Nigerian Naira','RWF'=>'Rwandan Franc','STN'=>'São Tomé Dobra','SCR'=>'Seychellois Rupee','SLE'=>'Sierra Leone Leone','SOS'=>'Somali Shilling','ZAR'=>'South African Rand','SSP'=>'South Sudanese Pound','SDG'=>'Sudanese Pound','TZS'=>'Tanzanian Shilling','TND'=>'Tunisian Dinar','UGX'=>'Ugandan Shilling','ZMW'=>'Zambian Kwacha','USD'=>'US Dollar','EUR'=>'Euro','GBP'=>'Pound Sterling','CHF'=>'Swiss Franc','JPY'=>'Japanese Yen','CNY'=>'Chinese Yuan','INR'=>'Indian Rupee','AUD'=>'Australian Dollar','CAD'=>'Canadian Dollar','NZD'=>'New Zealand Dollar','SGD'=>'Singapore Dollar','HKD'=>'Hong Kong Dollar','AED'=>'UAE Dirham','SAR'=>'Saudi Riyal','QAR'=>'Qatari Riyal','KWD'=>'Kuwaiti Dinar','BHD'=>'Bahraini Dinar','OMR'=>'Omani Rial','ILS'=>'Israeli New Shekel','TRY'=>'Turkish Lira','RUB'=>'Russian Ruble','BRL'=>'Brazilian Real','MXN'=>'Mexican Peso','ARS'=>'Argentine Peso','CLP'=>'Chilean Peso','COP'=>'Colombian Peso','PEN'=>'Peruvian Sol','KRW'=>'South Korean Won','THB'=>'Thai Baht','MYR'=>'Malaysian Ringgit','IDR'=>'Indonesian Rupiah','PHP'=>'Philippine Peso','VND'=>'Vietnamese Dong','PKR'=>'Pakistani Rupee','BDT'=>'Bangladeshi Taka','LKR'=>'Sri Lankan Rupee','NPR'=>'Nepalese Rupee','PLN'=>'Polish Zloty','SEK'=>'Swedish Krona','NOK'=>'Norwegian Krone','DKK'=>'Danish Krone','CZK'=>'Czech Koruna','HUF'=>'Hungarian Forint','RON'=>'Romanian Leu','UAH'=>'Ukrainian Hryvnia'] as $code => $label): ?>
                        <option value="<?php echo $code; ?>" <?php echo ($user['preferred_currency'] ?? 'USD') === $code ? 'selected' : ''; ?>><?php echo $code . ' — ' . $label; ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="form-help">Choose the currency used when prices and payment options are shown to you.</p>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="email_notifications" value="1" <?php echo ($user['email_notifications'] ?? 1) ? 'checked' : ''; ?>>
                    <span>Email Notifications</span>
                </label>
                <p class="form-help">Receive email notifications for new episodes and articles</p>
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="push_notifications" value="1" <?php echo ($user['push_notifications'] ?? 1) ? 'checked' : ''; ?>>
                    <span>Push Notifications</span>
                </label>
                <p class="form-help">Receive push notifications for updates</p>
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="newsletter_subscription" value="1" <?php echo ($user['newsletter_subscription'] ?? 1) ? 'checked' : ''; ?>>
                    <span>Newsletter Subscription</span>
                </label>
                <p class="form-help">Subscribe to our newsletter for weekly updates</p>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Settings</button>
                <a href="/TMZ%20PODCAST/user/dashboard.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</section>

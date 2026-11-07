<h2><?php _e('WebAuthn Passkeys', 'royal-sentinel'); ?></h2>
<table class="form-table">
    <tr>
        <th><label><?php _e('Registered Devices', 'royal-sentinel'); ?></label></th>
        <td>
            <div class="rs-passkeys-list">
                <?php if (empty($passkeys)) : ?>
                    <p class="description"><?php _e('No passkeys found for this account.', 'royal-sentinel'); ?></p>
                <?php else : ?>
                    <ul class="rs-passkey-items">
                        <?php foreach ($passkeys as $pk) : ?>
                            <li>
                                <strong><?php echo esc_html(isset($pk['name']) ? $pk['name'] : 'Device'); ?></strong>
                                <span>(Added: <?php echo esc_html($pk['created_at']); ?>)</span>
                                <button type="button" class="button rs-revoke-passkey" data-id="<?php echo esc_attr($pk['credentialId']); ?>"><?php _e('Revoke', 'royal-sentinel'); ?></button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="rs-passkey-actions">
                <input type="text" id="rs-new-passkey-name" class="regular-text" placeholder="<?php _e('Device Name (e.g., Mac TouchID)', 'royal-sentinel'); ?>" />
                <button type="button" id="rs-register-passkey" class="button button-primary"><?php _e('Register New Passkey', 'royal-sentinel'); ?></button>
            </div>
            
            <p class="description"><?php _e('Register a device passkey to securely log in without passwords. Great for 1-click logins!', 'royal-sentinel'); ?></p>
        </td>
    </tr>
</table>

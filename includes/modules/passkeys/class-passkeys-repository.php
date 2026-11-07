<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Webauthn\PublicKeyCredentialSourceRepository;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\PublicKeyCredentialSource;

class RS_Passkeys_Repository implements PublicKeyCredentialSourceRepository {

    public function findOneByCredentialId(string $publicKeyCredentialId): ?PublicKeyCredentialSource {
        // In a real database, we'd query by credential ID. Since we use user meta, we must search all.
        // For performance in large sites, a custom table is better, but user meta is requested.
        $users = get_users(array(
            'meta_key'     => 'rs_passkeys',
            'meta_compare' => 'EXISTS'
        ));

        $encodedId = base64_encode($publicKeyCredentialId);

        foreach ($users as $user) {
            $passkeys = get_user_meta($user->ID, 'rs_passkeys', true);
            if (is_array($passkeys)) {
                foreach ($passkeys as $pk) {
                    if (isset($pk['credentialId']) && $pk['credentialId'] === $encodedId) {
                        return self::unserializeSource($pk['source']);
                    }
                }
            }
        }
        return null;
    }

    public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array {
        $user_id = (int) $publicKeyCredentialUserEntity->getId();
        $passkeys = get_user_meta($user_id, 'rs_passkeys', true);
        $sources = [];
        if (is_array($passkeys)) {
            foreach ($passkeys as $pk) {
                $source = self::unserializeSource($pk['source']);
                if ($source instanceof PublicKeyCredentialSource) {
                    $sources[] = $source;
                }
            }
        }
        return $sources;
    }

    public function saveCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource): void {
        $user_id = (int) $publicKeyCredentialSource->getUserHandle();
        if (!$user_id) return;

        $passkeys = get_user_meta($user_id, 'rs_passkeys', true);
        if (!is_array($passkeys)) {
            $passkeys = [];
        }
        
        $encodedId = base64_encode($publicKeyCredentialSource->getPublicKeyCredentialId());
        
        $found = false;
        foreach ($passkeys as &$pk) {
            if (isset($pk['credentialId']) && $pk['credentialId'] === $encodedId) {
                $pk['source'] = serialize($publicKeyCredentialSource);
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $passkeys[] = [
                'credentialId' => $encodedId,
                'name'         => isset($_POST['device_name']) ? sanitize_text_field($_POST['device_name']) : 'Unknown Device',
                'source'       => serialize($publicKeyCredentialSource),
                'created_at'   => current_time('mysql')
            ];
        }
        
        update_user_meta($user_id, 'rs_passkeys', $passkeys);
    }
    
    private static function unserializeSource($serialized) {
        // web-auth lib handles its own serialization optimally in v3 via json or php serialize
        // Here we use native serialize
        return @unserialize($serialized);
    }
}

<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'freddyva_wp880' );

/** MySQL database username */
define( 'DB_USER', 'freddyva_wp880' );

/** MySQL database password */
define( 'DB_PASSWORD', '66[SKK1p-h' );

/** MySQL hostname */
define( 'DB_HOST', 'mysql3001.mochahost.com' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'zav7yzj9nbscfkstsmfajtcbtoyxafp0ptrcwoqmoz8j1t5xmxpwzfebkypmcowm' );
define( 'SECURE_AUTH_KEY',  '1extuqzo2tg61qblllwkpio6zsexqiish6fv7mmiikgcpxw2rdobcp59j9vbdgpa' );
define( 'LOGGED_IN_KEY',    'ka0ojpbw3u5dxdgltxyckmckturw9p0nkpwgl1ji3mwgigermgmce3zeovyxg7jq' );
define( 'NONCE_KEY',        'gygjueei1onfkkte0njvefcyzp7zjrerwogaedkonovp7phxiapbvnadvmgmsyxy' );
define( 'AUTH_SALT',        'm6kbzxafuvnsmvspavzgowfn1ll2dbgqi1sbi3ytfssxbpnrxx3xfacnudl3c1kq' );
define( 'SECURE_AUTH_SALT', 'z6jfelyflcfj5hp5dghhdsqz0d0llwlfahalafuyzhqoinwslxlgpgz7s3jkdgb1' );
define( 'LOGGED_IN_SALT',   '04bmhsspg7mqbl5xbmcyzdh35ykmbto51u5uwzu1hepn75bfevqcw8kpzm81z0ir' );
define( 'NONCE_SALT',       'gkbc1evybq8srjeh1jz0bc4utmgyhbzomruexh01tgkgpgwvrarnjgx7xjjuwm1v' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp5w_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

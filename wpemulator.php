<?php
	/**
		* Plugin Name: WP Emulator
		* Plugin URI: https://github.com/deeweb/wp-emulator
		* Description: Intégrez facilement un émulateur à votre site WordPress.
		* Version: 1.1
		* Author: deeweb
		* Author URI: https://deeweb.fr/
		*
		* Text Domain: wpemulator
		* Domain Path: languages
	*/
	
	function wpemulator_load_textdomain() {
		// Load translations from the languages directory.
		$locale = get_locale();
		
		// This filter is documented in /wp-includes/l10n.php.
		$locale = apply_filters( 'plugin_locale', $locale, 'wpemulator' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
		load_textdomain( 'wpemulator', WP_LANG_DIR . '/plugins/wpemulator-' . $locale . '.mo' );
		
		load_plugin_textdomain( 'wpemulator', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}
	add_action( 'plugins_loaded', 'wpemulator_load_textdomain' );
	
	// Crée le dossier wpemulator dans wp-content lors de l'activation du plugin
	function wpemulator_create_folder() {
		$upload_dir = wp_upload_dir();
		$wpemulator_dir = $upload_dir['basedir'] . '/wpemulator';
		
		if (!file_exists($wpemulator_dir)) {
			wp_mkdir_p($wpemulator_dir);
		}
	}
	register_activation_hook(__FILE__, 'wpemulator_create_folder');
	
	// Enregistre et charge le fichier CSS
	function wpemulator_enqueue_styles($hook) {
		// Vérifie si nous sommes sur la page de l'administration du plugin
		if ($hook != 'toplevel_page_wpemulator') {
			return;
		}
		wp_enqueue_style('wpemulator-styles', plugin_dir_url(__FILE__) . 'css/wpemulator.css');
	}
	add_action('admin_enqueue_scripts', 'wpemulator_enqueue_styles');
	
	// Ajoute les sous-menus pour le plugin
	function wpemulator_admin_menu() {
		add_menu_page(
        'WP Emulator',
        'WP Emulator',
        'manage_options',
        'wpemulator',
        'wpemulator_main_page',
        'dashicons-games',
        80
		);
	}
	add_action('admin_menu', 'wpemulator_admin_menu');
	
	// Fonction principale pour afficher les onglets et le contenu
	function wpemulator_main_page() {
		$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'list';
	?>
    <div class="wrap">
        <h1>WP Emulator</h1>
        <h2 class="nav-tab-wrapper">
            <a href="?page=wpemulator&tab=list" class="nav-tab <?php echo $current_tab === 'list' ? 'nav-tab-active' : ''; ?>"><?php _e('Liste des jeux' , 'wpemulator'); ?></a>
            <a href="?page=wpemulator&tab=upload" class="nav-tab <?php echo $current_tab === 'upload' ? 'nav-tab-active' : ''; ?>"><?php _e('Envoyer un jeu' , 'wpemulator'); ?></a>
            <a href="?page=wpemulator&tab=help" class="nav-tab <?php echo $current_tab === 'help' ? 'nav-tab-active' : ''; ?>"><?php _e('Aide' , 'wpemulator'); ?></a>
		</h2>
        <?php
			switch ($current_tab) {
				case 'upload':
                wpemulator_upload_game_page();
                break;
				case 'help':
                wpemulator_help_page();
                break;
				case 'list':
				default:
                wpemulator_list_games_page();
                break;
			}
		?>
	</div>
    <?php
	}
	
	// Fonction pour afficher la liste des jeux
	function wpemulator_list_games_page() {
		if (isset($_POST['wpemulator_delete_game'])) {
			wpemulator_delete_game(sanitize_text_field($_POST['wpemulator_delete_game']));
		}
		
		$upload_dir = wp_upload_dir();
		$wpemulator_dir = $upload_dir['basedir'] . '/wpemulator';
		$file_list = scandir($wpemulator_dir);
		$file_list = array_diff($file_list, array('.', '..'));
		$stored_titles = get_option('wpemulator_game_titles', array());
	?>
    <div class="wrap">
        <h3><?php _e('Liste des jeux' , 'wpemulator'); ?></h3>
        <table class="wpemulator-table">
            <thead>
                <tr>
                    <th><?php _e('Nom du jeu' , 'wpemulator'); ?></th>
                    <th><?php _e('Shortcode' , 'wpemulator'); ?></th>
                    <th><?php _e('Action' , 'wpemulator'); ?></th>
				</tr>
			</thead>
            <tbody>
                <?php foreach ($file_list as $file) : ?>
                <tr>
                    <td><?php echo esc_html($file); ?></td>
                    <td><code>[wpemulator game="<?php echo esc_attr($file); ?>" platform="<?php echo esc_attr(wpemulator_determine_core($file)); ?>"]</code></td>
                    <td>
                        <form method="post" style="display:inline-block;">
                            <input type="hidden" name="wpemulator_delete_game" value="<?php echo esc_attr($file); ?>">
                            <input type="submit" value="<?php _e('Supprimer' , 'wpemulator'); ?>" class="button button-danger" onclick="return confirm('<?php _e('Êtes-vous sûr de vouloir supprimer ce fichier ?' , 'wpemulator'); ?>');">
						</form>
					</td>
				</tr>
                <?php endforeach; ?>
			</tbody>
		</table>
	</div>
    <?php
	}
	
	// Fonction pour afficher la page d'upload
	function wpemulator_upload_game_page() {
		if (isset($_FILES['wpemulator_file'])) {
			$upload_dir = wp_upload_dir();
			$wpemulator_dir = $upload_dir['basedir'] . '/wpemulator';
			$file_name = $_FILES['wpemulator_file']['name'];
			$file_tmp = $_FILES['wpemulator_file']['tmp_name'];
			
			$stored_titles = get_option('wpemulator_game_titles', array());
			
			if (file_exists($wpemulator_dir . '/' . $file_name)) {
				echo '<div class="notice notice-error is-dismissible"><p>Erreur : Un fichier avec le nom "' . esc_html($file_name) . '" existe déjà.</p></div>';
				} elseif (!wpemulator_validate_extension($file_name)) {
				echo '<div class="notice notice-error is-dismissible"><p>Erreur : Extension de fichier non valide. Seules les extensions suivantes sont autorisées : zip, smc, sfc, fig, swc, bs, st, gba, gb, gbc, dmg, fds, nes, unif, unf, vb, vboy, nds, n64, z64, v64, u1, ndd, sms, smd, md, gg, pbp, chd.</p></div>';
				} else {
				if (move_uploaded_file($file_tmp, $wpemulator_dir . '/' . $file_name)) {
					if (pathinfo($file_name, PATHINFO_EXTENSION) == 'zip') {
						$zip = new ZipArchive;
						if ($zip->open($wpemulator_dir . '/' . $file_name) === TRUE) {
							// Extraction des fichiers
							for ($i = 0; $i < $zip->numFiles; $i++) {
								$filename = $zip->getNameIndex($i);
								$file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
								
								// Déterminer le chemin complet pour l'extraction
								$extract_to = $wpemulator_dir . '/' . $filename;
								$zip->extractTo($wpemulator_dir, array($filename));
								
								// Supprimer le fichier après l'extraction
								if (file_exists($extract_to)) {
									unlink($extract_to);
								}
							}
							$zip->close();
						}
					}
					
					// Mise à jour de la liste des jeux
					$game_title = pathinfo($file_name, PATHINFO_FILENAME);
					$stored_titles[$file_name] = $game_title;
					update_option('wpemulator_game_titles', $stored_titles);
					
					echo '<div class="notice notice-success is-dismissible"><p>Le fichier <b>' . esc_html($file_name) . '</b> a été téléchargé avec succès.</p></div>';
					} else {
					echo '<div class="notice notice-error is-dismissible"><p>Erreur : Impossible de déplacer le fichier téléchargé.</p></div>';
				}
			}
		}
	?>
    <div class="wrap">
        <h3><?php _e('Téléverser un jeu' , 'wpemulator'); ?></h3>
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="wpemulator_file" accept=".zip,.smc,.sfc,.fig,.swc,.bs,.st,.gba,.gb,.gbc,.dmg,.fds,.nes,.unif,.unf,.vb,.vboy,.nds,.n64,.z64,.v64,.u1,.ndd,.sms,.smd,.md,.gg,.pbp,.chd" required>
            <input type="submit" value="<?php _e('Téléverser' , 'wpemulator'); ?>" class="button button-primary">
		</form>
	</div>
    <?php
	}
	
	// Fonction pour afficher la page d'aide
	function wpemulator_help_page() {
		$systems_supported = array(
        'Super Nintendo (SNES)' => ['smc', 'sfc', 'fig', 'swc', 'bs', 'st'],
        'Game Boy Advance (GBA)' => ['gba'],
        'Game Boy (GB)' => ['gb', 'gbc', 'dmg'],
        'Nintendo Entertainment System (NES)' => ['fds', 'nes', 'unif', 'unf'],
        'Virtual Boy (VB)' => ['vb', 'vboy'],
        'Nintendo DS (NDS)' => ['nds'],
        'Nintendo 64 (N64)' => ['n64', 'z64', 'v64', 'u1', 'ndd'],
        'Sega Master System (SMS)' => ['sms'],
        'Sega Mega Drive / Genesis (MD)' => ['smd', 'md'],
        'Sega Game Gear (GG)' => ['gg'],
        'PlayStation (PSX)' => ['pbp', 'chd']
		);
	?>
    <div class="wrap">
        <h3><?php _e('Aide' , 'wpemulator'); ?></h3>
        <p><?php _e('Voici la liste complète des systèmes supportés par le plugin, avec leurs extensions de fichiers :' , 'wpemulator'); ?></p>
        <ul>
            <?php foreach ($systems_supported as $system => $extensions) : ?>
			<li>
				<strong><?php echo esc_html($system); ?></strong>: 
				<?php echo implode(', ', array_map('esc_html', $extensions)); ?>
			</li>
            <?php endforeach; ?>
		</ul>
	</div>
    <?php
	}
	
	// Fonction pour supprimer un fichier
	function wpemulator_delete_game($file_name) {
		$upload_dir = wp_upload_dir();
		$wpemulator_dir = $upload_dir['basedir'] . '/wpemulator/' . $file_name;
		
		if (file_exists($wpemulator_dir)) {
			unlink($wpemulator_dir);
			
			$stored_titles = get_option('wpemulator_game_titles', array());
			unset($stored_titles[$file_name]);
			update_option('wpemulator_game_titles', $stored_titles);
			
			echo '<div class="notice notice-success is-dismissible"><p>Le fichier ' . esc_html($file_name) . ' a été supprimé avec succès.</p></div>';
			} else {
			echo '<div class="notice notice-error is-dismissible"><p>Erreur : le fichier ' . esc_html($file_name) . ' n\'existe pas.</p></div>';
		}
	}
	
	// Fonction pour valider l'extension du fichier
	function wpemulator_validate_extension($file_name) {
		$valid_extensions = array_merge(
        ["zip"],
        ["smc", "sfc", "fig", "swc", "bs", "st"],
        ["gba"],
        ["gb", "gbc", "dmg"],
        ["fds", "nes", "unif", "unf"],
        ["vb", "vboy"],
        ["nds"],
        ["n64", "z64", "v64", "u1", "ndd"],
        ["sms"],
        ["smd", "md"],
        ["gg"],
        ["pbp", "chd"]
		);
		
		$ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
		
		return in_array($ext, $valid_extensions);
	}
	
	// Fonction pour déterminer la plateforme/core en fonction de l'extension du jeu
	function wpemulator_determine_core($game) {
		$extensions = array(
        'snes' => ["smc", "sfc", "fig", "swc", "bs", "st"],
        'gba' => ["gba"],
        'gb' => ["gb", "gbc", "dmg"],
        'nes' => ["fds", "nes", "unif", "unf"],
        'vb' => ["vb", "vboy"],
        'nds' => ["nds"],
        'n64' => ["n64", "z64", "v64", "u1", "ndd"],
        'segaMS' => ["sms"],
        'segaMD' => ["smd", "md"],
        'segaGG' => ["gg"],
        'psx' => ["pbp", "chd"]
		);
		
		if (pathinfo($game, PATHINFO_EXTENSION) == 'zip') {
			$zip = new ZipArchive;
			$core = '';
			if ($zip->open(wp_upload_dir()['basedir'] . '/wpemulator/' . $game) === TRUE) {
				for ($i = 0; $i < $zip->numFiles; $i++) {
					$filename = $zip->getNameIndex($i);
					$file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
					foreach ($extensions as $key => $exts) {
						if (in_array($file_ext, $exts)) {
							$core = $key;
							break 2;
						}
					}
				}
				$zip->close();
			}
			return $core;
			} else {
			$ext = strtolower(pathinfo($game, PATHINFO_EXTENSION));
			foreach ($extensions as $key => $exts) {
				if (in_array($ext, $exts)) {
					return $key;
				}
			}
		}
		return '';
	}
	
	// Shortcode pour afficher l'émulateur
	function wpemulator_shortcode($atts) {
		extract(shortcode_atts(
        array(
		'game' => '',
		'platform' => '',
        ), $atts
		));
		
		$core = wpemulator_determine_core($game);
		
		return '<div style="width:640px;height:480px;max-width:100%;margin: 20px auto;">
        <div id="game"></div>
        </div>
        <script type="text/javascript">
        EJS_player = "#game";
        EJS_core = "' . esc_js($core) . '";
        EJS_gameUrl = "' . esc_url(wp_upload_dir()['baseurl'] . '/wpemulator/' . esc_attr($game)) . '";
        EJS_pathtodata = "' . esc_url(plugin_dir_url(__FILE__) . 'emulatorjs/') . '";
        EJS_language = "af-FR";
        </script>
        <script src="' . esc_url(plugin_dir_url(__FILE__) . 'emulatorjs/loader.js') . '"></script>';
	}
	add_shortcode('wpemulator', 'wpemulator_shortcode');
?>
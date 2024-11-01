<?php /*

**************************************************************************

Plugin Name:  Templatedia
Plugin URI:   http://www.viper007bond.com/wordpress-plugins/templatedia/
Version:      1.1.0
Description:  Create and manage dynamic templates for use in posts and pages. Template format based on that of <a href="http://www.mediawiki.org/wiki/Help:Templates">MediaWiki</a>, the software behind <a href="http://www.wikipedia.org/">Wikipedia</a>.
Author:       Viper007Bond
Author URI:   http://www.viper007bond.com/

**************************************************************************/

class Templatedia {
	var $templates;
	var $encodedtemplates = array();
	var $variables;
	var $error;
	var $is2point1plus;
	var $menustub = 'templatedia'; // Identifier for the admin menu
	var $folder = 'wp-content/plugins/templatedia';

	// Initialization stuff
	function Templatedia() {
		// Are we running WordPress 2.1+ ?
		$this->is2point1plus = ( class_exists('WP_Scripts') ) ? TRUE : FALSE;

		// Load up the localization file if we're using WordPress in a different language
		// Place it in the "localization" folder and name it "templatedia-[value in wp-config].mo"
		load_plugin_textdomain('templatedia', $this->folder . '/localization');

		// Register our hooks
		add_action( 'admin_menu', array(&$this, 'AddMenus') );
		add_filter( 'the_content', array(&$this, 'PreserveTemplates_Encode'), 4 ); // Encode template request contents
		add_filter( 'the_content', array(&$this, 'PreserveTemplates_Decode'), 7006 ); // Decode template request contents
		add_filter( 'the_content', array(&$this, 'SearchAndReplace'), 7007 ); // Process and replace template requests
		add_action( 'init',  array(&$this, 'Init') ); // Allow all other plugins to load before using filtered content

		// Filters for Templatedia. These are added as filters so other plugins can remove them.
		add_filter( 'templatedia_pre_template', array(&$this, 'wikitext') ); // Handle basic Wikitext in template HTML before inserting into content
		add_filter( 'templatedia_pre_template', 'wptexturize' ); // Make template output look pretty too
		add_filter( 'templatedia_paramvalue', 'nl2br' ); // Convert line breaks in template call (in the post) to actual line breaks

		// If one of this plugin's forms has been submitted or if the user is attempting to do an action, hook in
		if ( $_POST['templatedia_action'] || ( $this->menustub === $_GET['page'] && !empty($_GET['action']) ) ) add_action('init', array(&$this, 'HandleForms'));
	}


	// Register our admin menu
	function AddMenus() {
		add_management_page( __('Templatedia Template Management', 'templatedia'), __('Templates', 'templatedia'), 'manage_options', $this->menustub, array(&$this, 'AdminPage') );
	}


	// This function runs on all pages at 'init', i.e. after all plugins have loaded
	function Init() {
		// This plugin supports some pre-defined variables -- let's create the static ones now
		// If you're looking to modify/add this, use the "templatedia_variables" filter rather than editing this file
		// The "mediawiki-variables.php" file contains an example on how to do so
		$this->variables = array(
			'WP:BLOGNAME'     => array( 'output' => get_bloginfo( 'name' ), 'description' => __('Website title as set in General Options', 'templatedia') ),
			'WP:URL'          => array( 'output' => get_bloginfo( 'url' ), 'description' => __('The URL to your website with no trailing slash', 'templatedia') ),
			'WP:WPURL'        => array( 'output' => get_bloginfo( 'wpurl' ), 'description' => __('The URL to your WordPress install with no trailing slash', 'templatedia') ),
			'WP:WPCONTENTURL' => array( 'output' => get_bloginfo( 'wpurl' ) . '/wp-content', 'description' => __('The URL to your "wp-content" folder with no trailing slash', 'templatedia') ),
		);
	}


	// Handle form submissions and such
	function HandleForms() {
		global $post;

		$this->MaybeLoadTemplates( TRUE );

		// Add new template
		if ( 'add' == $_POST['templatedia_action'] ) {
			check_admin_referer('templatedia_add');

			$stub = trim( strtolower( strip_tags( stripslashes( $_POST['templatedia_stub'] ) ) ) );

			// Make sure we have a new stub, the rest is optional
			if ( empty($stub) )
				return $this->error = __('You must enter a stub for the template.', 'templatedia');
			if ( !empty($this->templates[$stub]) ) 
				return $this->error = sprintf( __('A template with this stub already exists. Please pick a different stub name or <a href="%s">edit the existing one</a>.', 'templatedia'), '?page=' . $this->menustub . '&amp;action=edit&amp;stub=' . urlencode($stub) );

			// Check for variable / template name conflicts (we need to loop due to case sensitivity)
			$post = get_posts('numberposts=1'); $post = $post[0];
			$variables = apply_filters( 'templatedia_variables', $this->variables + $this->DynamicVariables() );
			foreach ( (array) $variables as $variable => $output ) {
				if ( strtolower($variable) == $stub ) return $this->error = __('A variable with this stub name already exists. Please pick a different stub name to avoid a conflict.');
			}

			// All is well, so save it!
			$this->templates[$stub] = array(
				'editable' => TRUE,
				'description' => stripslashes( $_POST['templatedia_description'] ),
				'template'    => stripslashes( $_POST['templatedia_template'] ),
			);
			update_option( 'templatedia_templates', $this->templates );

			wp_redirect( '?page=' . $this->menustub . '&added=1' );
			exit();
		}

		// Edit existing template
		if ( 'edit' == $_POST['templatedia_action'] ) {
			check_admin_referer('templatedia_edit');

			$stub = trim( strtolower( strip_tags( stripslashes( $_POST['templatedia_stub'] ) ) ) );

			// Make sure we have a new stub, the rest is optional
			if ( empty($stub) ) return $this->error = __('You must enter a stub for the template.', 'templatedia');

			// Check for variable / template name conflicts (we need to loop due to case sensitivity)
			$post = get_posts('numberposts=1'); $post = $post[0];
			$variables = apply_filters( 'templatedia_variables', $this->variables + $this->DynamicVariables() );
			foreach ( (array) $variables as $variable => $output ) {
				if ( strtolower($variable) == $stub ) return $this->error = __('A variable with this stub name already exists. Please pick a different stub name to avoid a conflict.');
			}

			// If the stub was changed, make sure the new stub isn't already taken
			if ( $_POST['templatedia_oldstub'] != $stub && !empty($this->templates[$stub]) ) {
				return $this->error = sprintf( __('A template with this stub already exists. Please pick a different stub name or <a href="%s">edit the existing one</a>.', 'templatedia'), '?page=' . $this->menustub . '&amp;action=edit&amp;stub=' . urlencode($stub) );
			}

			// All is well, so save it!
			unset( $this->templates[$_POST['templatedia_oldstub']] ); // Incase the stub was changed, get rid of the old or "old" one
			$this->templates[$stub] = array(
				'editable' => TRUE,
				'description' => stripslashes( $_POST['templatedia_description'] ),
				'template'    => stripslashes( $_POST['templatedia_template'] ),
			);
			ksort( $this->templates );
			update_option( 'templatedia_templates', $this->templates );

			wp_redirect( '?page=' . $this->menustub . '&edited=1' );
			exit();
		}

		// Delete template
		if ( 'delete' == $_GET['action'] ) {
			$stub = stripslashes( $_GET['stub'] );

			check_admin_referer( 'templatedia_delete_' . $stub );

			if ( empty($this->templates[$stub]) )
				return $this->error = __( "You can't delete a template that doesn't exist!", 'templatedia' );

			if ( TRUE !== $this->templates[$stub]['editable'] )
				return $this->error = __( 'This template is not deletable as it is a special template that was added by a plugin. Disable the plugin that added it if you wish to remove this template.', 'templatedia' );

			unset($this->templates[$stub]);
			update_option( 'templatedia_templates', $this->templates );

			wp_redirect( '?page=' . $this->menustub . '&deleted=1' );
			exit();
		}


		// Delete all templates (this is never called, but can be used manually)
		if ( 'clearall' == $_GET['action'] ) {
			// Make a confirmation screen to avoid any XSS exploits
			check_admin_referer('templatedia_clearalltemplates');

			delete_option( 'templatedia_templates' );

			$this->MaybeLoadTemplates();

			wp_redirect( '?page=' . $this->menustub . '&alldeleted=1' );
			exit();
		}
	}


	// Function that makes sure the $templates variable is set
	function MaybeLoadTemplates( $forceload = FALSE ) {
		if ( !is_array($this->templates) || FALSE != $forceload ) $this->templates = get_option( 'templatedia_templates' );

		// Still no templates found? Alright, create an example.
		if ( !is_array($this->templates) ) {
			$this->templates = array(
				'bluebox' => array(
					'editable' => TRUE,
					'description' => __('An example template with the parameter "text" and an include of the "wordpresslogo" template. You can safely delete this.', 'templatedia'),
					'template' => "<span style=\"display: block; background-color: #CFEBF7; padding: 10px;\">\n\t" . __('The parameter &quot;<code>text</code>&quot; has been set to: <code>{{{text|NOTHING}}}</code>', 'templatedia') . "<br />\n\t<br />\n\t" . __('Here is the template &quot;<code>wordpresslogo</code>&quot;:', 'templatedia') . "<br />\n\t{{wordpresslogo}}\n</span>",
				),
				'diggthis' => array(
					'editable' => TRUE,
					'description' => __('An example template that allows you to add a Digg button to a post. You can safely delete this.', 'templatedia'),
					'template' => "<span style=\"display: block; float: right;\">\n\t<script type=\"text/javascript\">\n\t\tdigg_url = '{{WP:POSTLINK}}';\n\t\tdigg_title = '{{WP:POSTTITLE}}';\n\t</script>\n\t<script src=\"http://digg.com/tools/diggthis.js\" type=\"text/javascript\"></script>\n</span>",
				),
				'imgborder' => array(
					'editable' => TRUE,
					'description' => __('An example template that allows you to display an image of your choosing with a border. You can safely delete this.', 'templatedia'),
					'template' => '<img src="{{{1}}}" alt="Image" style="padding: 5px; border: 5px solid #CFEBF7; vertical-align: middle;" />',
				),
				'wordpresslogo' => array(
					'editable' => TRUE,
					'description' => __('An example template that displays the WordPress logo. You can safely delete this.', 'templatedia'),
					'template' => '<a href="http://wordpress.org/"><img src="{{WP:WPURL}}/wp-admin/images/wordpress-logo.png" alt="WordPress" /></a>',
				),
			);

			update_option( 'templatedia_templates', $this->templates );
		}

		// Allow plugins to add templates
		if ( FALSE == $forceload ) $this->templates = apply_filters( 'templatedia_templates', $this->templates );
	}


	// Create dynamic variables
	function DynamicVariables() {
		global $post;

		if ( empty($post->ID) ) return array();

		return array(
			'WP:POSTTITLE' => array(
				'output' => the_title( '', '', FALSE ),
				'description' => __('The title of the current post or page', 'templatedia')
			),
			'WP:POSTLINK' => array(
				'output' => get_permalink(),
				'description' => __('The full URL to the current post or page', 'templatedia')
			),
			'WP:POSTDATE'  => array(
				'output' => get_the_time( get_option( 'date_format' ) ),
				'description' => __('The date of when the current post or page was published formatted as specified in the General Options', 'templatedia')
			),
			'WP:POSTTIME'  => array(
				'output' => get_the_time( get_option( 'time_format' ) ),
				'description' => __('The time of when the current post or page was published formatted as specified in the General Options', 'templatedia')
			),
			'WP:POSTTYPE'  => array(
				'output' => $post->post_type,
				'description' => __('Displays the type of post this is, i.e. "post" or "page"', 'templatedia')
			),
			'WP:POSTID'    => array(
				'output' => $post->ID,
				'description' => __('The ID of the current post or page', 'templatedia')
			),
		);
	}


	// Template management page for the admin area
	function AdminPage() {
		switch ( $_GET['action'] ) {

			##################################################

			// Create new template form
			case 'add' :
				$this->AdminTemplateForm();
				break;
			
			##################################################

			// Edit existing template form
			case 'edit' :
				$stub = stripslashes( $_GET['stub'] );

				$this->MaybeLoadTemplates();

				if ( empty($this->templates[$stub]) ) {
					$this->error = sprintf( __('No template with the stub &quot;%s&quot; exists.', 'templatedia'), attribute_escape($stub) );
				} else {
					$this->AdminTemplateForm($stub);
					break;
				}

			##################################################

			default :
				$this->MaybeLoadTemplates();

				ksort( $this->templates );
		?>

<?php if ( !empty($this->error) ) : ?><div id="message" class="error"><p><strong><?php echo $this->error; ?></strong></p></div>
<?php elseif ( !empty($_GET['added']) ) : ?><div id="message" class="updated fade"><p><strong><?php _e('Template added.', 'templatedia'); ?></strong></p></div>
<?php elseif ( !empty($_GET['edited']) ) : ?><div id="message" class="updated fade"><p><strong><?php _e('Template edited.', 'templatedia'); ?></strong></p></div>
<?php elseif ( !empty($_GET['deleted']) ) : ?><div id="message" class="updated fade"><p><strong><?php _e('Template deleted.', 'templatedia'); ?></strong></p></div>
<?php elseif ( !empty($_GET['alldeleted']) ) : ?><div id="message" class="updated fade"><p><strong><?php _e('All templates have been deleted and the default examples restored.', 'templatedia'); ?></strong></p></div>
<?php endif; ?>

<div class="wrap">
	<h2><?php echo __('Templatedia', 'templatedia') . ' &raquo; ' . sprintf( __('Manage Templates (<a href="%s">add new</a>)', 'templatedia'), '?page=' . $this->menustub . '&amp;action=add' ); ?></h2>

<?php if ( empty($this->templates) ) : ?>
		<?php printf( __('No templates currently exist. <a href="%s">Go make one</a>!', 'templatedia'), '?page=' . $this->menustub . '&amp;action=add' ); ?>
<?php else : ?>
	<table <?php echo ( TRUE == $this->is2point1plus ) ? 'class="widefat"' : 'width="100%" cellpadding="3" cellspacing="3"'; ?>>
		<thead>
			<tr>
				<th scope="col"><div style="text-align: center"><?php _e('Stub', 'templatedia'); ?></div></th>
				<th scope="col"><?php _e('Description', 'templatedia'); ?></th>
				<th scope="col"></th>
				<th scope="col"></th>
			</tr>
		</thead>
		<tbody>
<?php foreach ( (array) $this->templates as $stub => $template ) : $rowclass = ( '' == $rowclass ) ?' class="alternate"' : ''; ?>
			<tr<?php echo $rowclass; ?>>
				<td><div style="text-align: center"><code><?php echo $stub; ?></code></div></td>
				<td><?php echo wptexturize ( $template['description'] ); ?></td>
				<td><a href="?page=<?php echo $this->menustub; ?>&amp;action=edit&amp;stub=<?php echo urlencode($stub); ?>" class="edit"><?php _e('Edit'); ?></a></td>
				<td><a href="?<?php echo wp_nonce_url( 'page=' . $this->menustub . '&amp;action=delete&amp;stub=' . urlencode($stub), 'templatedia_delete_' . $stub ); ?>" class="delete" onclick="return confirm('<?php echo js_escape( sprintf( __("You are about to delete this template '%s'.\n'OK' to delete, 'Cancel' to stop.", 'templatedia'), $stub ) ); ?>');"><?php _e('Delete'); ?></a></td>
			</tr>
<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; // endif $this->templates ?>
</div>
<?php
				break;

			##################################################

		}
	}


	// Creates and displays a form used for both creating and editing a template
	function AdminTemplateForm( $stub = FALSE ) {
		global $post;

		$action = 'add';
		$button = __('Add Template', 'templatedia');

		if ( FALSE !== $stub ) {
			$this->MaybeLoadTemplates();
			if ( !empty($this->templates[$stub]) ) {
				$action = 'edit';
				$button = __('Edit Template', 'templatedia');
			}
		}

		$template_opener = apply_filters( 'templatedia_template_opener', '{{' );
		$template_closer = apply_filters( 'templatedia_template_closer', '}}' );

		// Load the latest post to use it as an example for the variables
		$post = get_posts('numberposts=1');
		$post = $post[0];

		$variables = apply_filters( 'templatedia_variables', $this->variables + $this->DynamicVariables() );

		$oldstub = $stub;
		$description = $this->templates[$stub]['description'];
		$template = $this->templates[$stub]['template'];
		$editable = ( FALSE !== $stub ) ? $this->templates[$stub]['editable'] : TRUE;

		if ( TRUE !== $editable ) $this->error = __( 'This template is not editable as it is a special template that was added by a plugin.', 'templatedia' );

		// If a form submit failed, repopulate the fields
		if ( !empty($_POST['templatedia_stub']) )        $stub        = trim( strtolower( strip_tags( stripslashes( $_POST['templatedia_stub'] ) ) ) );
		if ( !empty($_POST['templatedia_oldstub']) )     $oldstub     = stripslashes( $_POST['templatedia_oldstub'] );
		if ( !empty($_POST['templatedia_description']) ) $description = stripslashes( $_POST['templatedia_description'] );
		if ( !empty($_POST['templatedia_template']) )    $template    = stripslashes( $_POST['templatedia_template'] );

		?>

<?php wp_print_scripts( 'quicktags' ); ?>

<script type="text/javascript">
/* <![CDATA[ */
	function TemplatediaInsertVariable( varname ) {
		edInsertContent( document.getElementById('templatedia_template'), '<?php echo $template_opener; ?>' + varname + '<?php echo $template_closer; ?>' );
	}
/* ]]> */
</script>

<span id="templatedia"></span>

<?php if ( !empty($this->error) ) : ?><div id="message" class="error"><p><strong><?php echo $this->error; ?></strong></p></div><?php endif; ?>

<div class="wrap">
	<h2><?php echo __('Templatedia', 'templatedia') . ' &raquo; ' . $button; ?></h2>

	<form method="post" action="">
		<input type="hidden" name="templatedia_action" value="<?php echo $action; ?>" />
<?php if ( FALSE !== $stub ) : ?>		<input type="hidden" name="templatedia_oldstub" value="<?php echo attribute_escape($oldstub); ?>" /><?php echo "\n"; endif; ?>
<?php wp_nonce_field( 'templatedia_' . $action ); ?>


		<table class="editform" width="100%" cellspacing="2" cellpadding="5">
			<tr>
				<th width="200" scope="row"><label for="templatedia_stub"><?php _e('Template Stub:', 'templatedia'); ?></label></th>
				<td>
					<input name="templatedia_stub" id="templatedia_stub" type="text" value="<?php echo attribute_escape($stub); ?>" size="18" <?php if ( TRUE !== $editable ) echo ' readonly="readonly"'; ?>/>
					&nbsp;<?php _e("A unique identifier for this template that'll be used to call it. Keep it simple.", 'templatedia'); ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="templatedia_description"><?php _e('Template Description:', 'templatedia'); ?></label></th>
				<td><input name="templatedia_description" id="templatedia_description" type="text" value="<?php echo attribute_escape($description); ?>" size="40" style="width: 97%;" <?php if ( TRUE !== $editable ) echo ' readonly="readonly"'; ?>/></td>
			</tr>
			<tr>
				<th scope="row" valign="top"><label for="templatedia_template"><?php _e('Template Code:', 'templatedia'); ?></label><p style="font-weight:normal"><?php _e('HTML is allowed', 'templatedia'); ?></p></th>
				<td>
					<textarea name="templatedia_template" id="templatedia_template" rows="20" cols="50" style="width: 97%;"<?php if ( TRUE !== $editable ) echo ' readonly="readonly"'; ?>><?php echo attribute_escape($template); ?></textarea>
					<p><?php _e("Be careful and make sure to no get stuck in an infinite template include loop! For example, don't include template A in template B which has template A already included in it.", 'templatedia'); ?></p>
				</td>
			</tr>
		</table>

		<p class="submit"><input type="submit" name="submit" value="<?php echo $button . ' ' . __('&raquo;', 'templatedia'); ?>" <?php if ( TRUE !== $editable ) echo ' disabled="disabled"'; ?>/></p>
	</form>
</div>

<div class="wrap">
	<h2><?php _e('Available Variables', 'templatedia'); ?></h2>

	<p><?php _e('Click on one of these variables to insert it into the template. The latest published post has been used as an example for the post-specfic variables.', 'templatedia'); ?></p>

	<table <?php echo ( TRUE == $this->is2point1plus ) ? 'class="widefat"' : 'width="100%" cellpadding="3" cellspacing="3"'; ?>>
		<thead>
			<tr>
				<th scope="col"><div style="text-align: center"><?php _e('Variable', 'templatedia'); ?></div></th>
				<th scope="col"><div style="text-align: center"><?php _e('Example Display', 'templatedia'); ?></div></th>
				<th scope="col"><?php _e('Description', 'templatedia'); ?></th>
			</tr>
		</thead>
		<tbody>
<?php foreach ( (array) $variables as $variable => $output ) : $rowclass = ( '' == $rowclass ) ?' class="alternate"' : ''; ?>
			<tr<?php echo $rowclass; ?>>
				<td><div style="text-align: center"><code><a href="#templatedia" title="<?php _e('Click to insert into template', 'templatedia'); ?>" onclick="TemplatediaInsertVariable('<?php echo $variable; ?>');"><?php echo $template_opener . $variable . $template_closer; ?></a></code></div></td>
				<td><div style="text-align: center"><code><?php echo $output['output']; ?></code></div></td>
				<td><?php echo wptexturize($output['description']); ?></td>
			</tr>
<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php
	}


	// Ensure that any templates requests the user makes don't get mangled by WordPress *sigh*
	// This and it's sister function aren't the prettiest / most effect, but hey, it seems to get the job done
	function PreserveTemplates_Encode( $content ) {
		global $post;

		$template_opener = apply_filters( 'templatedia_template_opener', '{{' );
		$template_closer = apply_filters( 'templatedia_template_closer', '}}' );
		$template_opener_quoted = preg_quote( $template_opener );
		$template_closer_quoted = preg_quote( $template_closer );

		// Find all template requests
		preg_match_all( '|' . $template_opener_quoted . '([^' . $template_closer_quoted . ']+)' . $template_closer_quoted . '|i', $content, $matches, PREG_SET_ORDER );

		if ( empty($matches) ) return $content; // No templates found, we can stop here

		// Loop through each template request
		foreach ( (array) $matches as $match ) {
			$id = uniqid( mt_rand() . '.', TRUE ); // Get a really unique ID for this template request
			$this->encodedtemplates[$id] = $match[1];

			// Temporarily replace the template call with it's ID
			$content = str_replace( $match[0], $template_opener . '[templatediaencoded]' . $id . '[/templatediaencoded]' . $template_closer, $content );
		}

		return $content;
	}


	// Reverse what PreserveTemplates_Encode() did
	function PreserveTemplates_Decode( $content ) {
		global $post;

		$template_opener = apply_filters( 'templatedia_template_opener', '{{' );
		$template_closer = apply_filters( 'templatedia_template_closer', '}}' );
		$template_opener_quoted = preg_quote( $template_opener );
		$template_closer_quoted = preg_quote( $template_closer );

		// Find all template requests
		preg_match_all( '|' . $template_opener_quoted . '\[templatediaencoded\]([^' . $template_closer_quoted . ']+)\[\/templatediaencoded\]' . $template_closer_quoted . '|i', $content, $matches, PREG_SET_ORDER );

		if ( empty($matches) ) return $content; // No templates found, we can stop here

		// Loop through each template request and replace the ID with the original content
		foreach ( (array) $matches as $match ) {
			if ( !empty($this->encodedtemplates[$match[1]]) )
				$content = str_replace( $match[0], $template_opener . $this->encodedtemplates[$match[1]] . $template_closer, $content );
		}

		return $content;
	}


	// Search for template requests in the given string and replace them with the actual templates
	function SearchAndReplace( $content ) {
		global $post;

		$this->MaybeLoadTemplates();

		// Allow plugins to modify the template and parameter wrappers (be careful!)
		$template_opener = apply_filters( 'templatedia_template_opener', '{{' );
		$template_closer = apply_filters( 'templatedia_template_closer', '}}' );
		$parameter_opener = apply_filters( 'templatedia_parameter_opener', '{{{' );
		$parameter_closer = apply_filters( 'templatedia_parameter_closer', '}}}' );

		// Quote them for use in regex
		$template_opener_quoted = preg_quote( $template_opener );
		$template_closer_quoted = preg_quote( $template_closer );
		$parameter_opener_quoted = preg_quote( $parameter_opener );
		$parameter_closer_quoted = preg_quote( $parameter_closer );

		// Finish creating the variables with some post/page specific ones
		$variables = $this->variables;
		if ( !empty($post->ID) ) $variables = $variables + $this->DynamicVariables();
		$variables = apply_filters( 'templatedia_variables', $variables );

		// Search for and replace any variables in the content itself
		if ( !empty($variables) && is_array($variables) ) {
			$svarstubs = array();
			$svarcontents = array();
			foreach ( $variables as $svarstub => $svarcontent ) {
				$svarstubs[] = $template_opener . $svarstub . $template_closer;
				$svarcontents[] = $svarcontent['output'];
			}
			$content = $this->strireplace( $svarstubs, $svarcontents, $content );
		}

		// If there's no templates, there's no point in continuing any further as we can't replace anything
		if ( empty($this->templates) ) return $content;

		// Find all template requests and loop through them
		preg_match_all( '|' . $template_opener_quoted . '([^' . $template_closer_quoted . ']+)' . $template_closer_quoted . '|i', $content, $matches, PREG_SET_ORDER );
		if ( empty($matches) ) return $content; // No templates found, we can stop here
		$handledrequests = array();
		foreach ( (array) $matches as $match ) {
			// Since it's possible for $match to get modified by a filter, we need to store the original template request for later
			$templaterequest = $match[0];

			// Allow plugins to pre-process any matches
			$match = apply_filters( 'templatedia_template_match', $match );

			// If this template request is a duplicate, skip it and save some CPU work
			if ( in_array($match, $handledrequests) ) continue;
			// Otherwise record that we've handled it
			$handledrequests[] = $match;

			// Explode the template request into the stub and any entered parameters
			$matchparts = explode( '|', $match[1] );

			// The stub is always the first part
			$stub = strtolower( $matchparts[0] );

			// If the template requested doesn't exist, keep going
			if ( empty($this->templates[$stub]) ) continue;

			// Make our life easier
			$template = $this->templates[$stub]['template'];

			// If we have some custom parameters set, handle them
			if ( !empty($matchparts[1]) ) {

				// Go through each entered parameter and replace it
				$paramnum = 0;
				foreach ( $matchparts as $param ) {
					// Skip the stub
					if ( 0 == $paramnum ) {
						$paramnum++;
						continue;
					}

					// Parse the parameter name and value and save it to an array
					if ( FALSE !== strpos($param, '=') ) {
						parse_str($param, $temp);
						$paramname = trim( key($temp) );
						$paramvalue = stripslashes( $temp[$paramname] );

						if ( empty($paramname) ) continue; // It had an equal sign but no name, ignore it as if it didn't exist
					} else {
						$paramname = $paramnum;
						$paramvalue = $param;
					}
					$paramvalue = trim($paramvalue);

					// Blank value, skip it and maybe replace it with the default later
					if ( empty($paramvalue) ) {
						$paramnum++;
						continue;
					}

					// Just for debug purposes
					$paramdebug[$paramnum] = array(
						'name' => $paramname,
						'value' => $paramvalue,
					);

					// Okay, this is a real parameter, advance the counter
					$paramnum++;

					$paramvalue = apply_filters( 'templatedia_paramvalue', $paramvalue );

					// Parse the template's content and replace the parameters
					$paramname = preg_quote($paramname);
					preg_match(	'/' . $parameter_opener_quoted . '(' . $paramname . '|' . $paramname . '\|([^' . $parameter_closer_quoted . ']+))' . $parameter_closer_quoted . '/', $template, $matchstring );
					$template = str_replace( $parameter_opener . $matchstring[1] . $parameter_closer, $paramvalue, $template ); // We don't use preg_replace() as having like "$4" in $template made it wonky
				}
			}

			// Search for and replace variables in the template
			$template = $this->strireplace( $svarstubs, $svarcontents, $template );

			// Find any variables in the template that weren't set and remove them or replace them with their defaults
			preg_match_all( '|' . $parameter_opener_quoted . '([^' . $parameter_closer_quoted . ']+)' . $parameter_closer_quoted . '|i', $template, $leftovers, PREG_SET_ORDER );
			if ( !empty($leftovers) ) {
				foreach ( $leftovers as $leftover ) {
					// If there's a default value, grab it
					$barpos = strpos($leftover[1], '|');
					$paramvalue = ( FALSE !== $barpos ) ? substr($leftover[1], $barpos + 1) : '';

					$template = str_replace( $leftover[0], $paramvalue, $template);
				}
			}

			// Check the rest of the template for links to other templates and replace any that are found
			preg_match_all( '|' . $template_opener_quoted . '([^' . $template_closer_quoted . ']+)' . $template_closer_quoted . '|i', $template, $matches, PREG_SET_ORDER );
			while ( !empty($matches) ) {
				$template = $this->SearchAndReplace($template);
				preg_match_all( '|' . $template_opener_quoted . '([^' . $template_closer_quoted . ']+)' . $template_closer_quoted . '|i', $template, $matches, PREG_SET_ORDER );
			}

			$template = apply_filters( 'templatedia_pre_template', $template );

			// Replace the template request with the processed template
			$content = str_replace( $templaterequest, $template, $content );
		}

		return $content;
	}


	// A very simple and basic Wikitext parser
	function wikitext( $content ) {
		$searches = array(
			"/'''([^']+)'''/", // bold
			"/''([^']+)''/", // italics
			'/<br>/i', // bad line breaks
		);

		$replaces = array(
			'<strong>$1</strong>',
			'<em>$1</em>',
			'<br />',
		);

		$content = preg_replace( $searches , $replaces, $content );
	
		return $content;
	}


	// If PHP5+, use str_ireplace(), otherwise do something else that'll accomplish what this plugin is looking for
	function strireplace( $search, $replace, $subject ) {
		if ( function_exists('str_ireplace') ) {
			$subject =  str_ireplace( $search, $replace, $subject );
		}
		
		// Ugh, pre-PHP5 (upgrade, people! PHP4 is dead!)
		else {
			if ( is_array($search) && is_array($replace) ) {
				$count = 0;
				foreach ( $search as $foobar ) {
					$subject = preg_replace( '/' . preg_quote( $search[$count] ) . '/i', $replace[$count], $subject);
					$count++;
				}
			} else {
				// This isn't a perfect replication function and shouldn't be used as such, tsk tsk
				$subject = preg_replace( '/' . preg_quote( $search, '/' ) . '/i', $replace, $subject);
			}
		}

		return $subject;
	}
}

// Lastly, initiate this plugin's PHP class
$Templatedia = new Templatedia();

?>
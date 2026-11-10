<?php
/**
 * Plugin Name:       Proyectos Portfolio
 * Description:       Añade un tipo de contenido "Proyectos", un shortcode [proyectos] para mostrarlos y una página de ajustes.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Mario
 * License:           GPL-2.0-or-later
 * Text Domain:       proyectos-portfolio
 */

// Seguridad: si alguien abre este archivo directamente desde el navegador
// (sin pasar por WordPress), cortamos aquí.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Constantes con la ruta y la URL del plugin, para no repetirlas.
define( 'PP_VERSION', '1.0.0' );
define( 'PP_URL', plugin_dir_url( __FILE__ ) );

/* =========================================================================
 * 1. CUSTOM POST TYPE "proyecto"
 *    Crea el menú "Proyectos" en el admin, igual que "Entradas" o "Páginas".
 * ========================================================================= */
function pp_registrar_cpt() {
	$etiquetas = array(
		'name'               => 'Proyectos',
		'singular_name'      => 'Proyecto',
		'add_new'            => 'Añadir nuevo',
		'add_new_item'       => 'Añadir nuevo proyecto',
		'edit_item'          => 'Editar proyecto',
		'new_item'           => 'Nuevo proyecto',
		'view_item'          => 'Ver proyecto',
		'search_items'       => 'Buscar proyectos',
		'not_found'          => 'No hay proyectos',
		'not_found_in_trash' => 'No hay proyectos en la papelera',
		'menu_name'          => 'Proyectos',
	);

	register_post_type( 'proyecto', array(
		'labels'       => $etiquetas,
		'public'       => true,                 // Visible en la web y en el admin.
		'has_archive'  => true,                 // Crea la página /proyectos/ con todos.
		'rewrite'      => array( 'slug' => 'proyectos' ),
		'menu_icon'    => 'dashicons-portfolio',
		'show_in_rest' => true,                 // Permite usar el editor de bloques.
		'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
	) );
}
add_action( 'init', 'pp_registrar_cpt' );

// Al activar el plugin, WordPress tiene que "aprender" la URL /proyectos/.
// flush_rewrite_rules() regenera las URLs. Solo se hace al activar/desactivar
// porque es una operación costosa.
function pp_activar() {
	pp_registrar_cpt();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'pp_activar' );

function pp_desactivar() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'pp_desactivar' );

/* =========================================================================
 * 2. CAMPOS EXTRA (META BOX): enlace y tecnologías de cada proyecto
 * ========================================================================= */
function pp_anadir_meta_box() {
	add_meta_box(
		'pp_datos_proyecto',       // ID interno.
		'Datos del proyecto',      // Título de la caja.
		'pp_pintar_meta_box',      // Función que pinta el HTML.
		'proyecto',                // Solo en el CPT "proyecto".
		'side'                     // Columna lateral.
	);
}
add_action( 'add_meta_boxes', 'pp_anadir_meta_box' );

function pp_pintar_meta_box( $post ) {
	// Nonce: un código de un solo uso que demuestra que el formulario
	// viene de nuestro admin y no de otra web (protección CSRF).
	wp_nonce_field( 'pp_guardar_datos', 'pp_nonce' );

	$enlace       = get_post_meta( $post->ID, '_pp_enlace', true );
	$tecnologias  = get_post_meta( $post->ID, '_pp_tecnologias', true );
	?>
	<p>
		<label for="pp_enlace"><strong>Enlace</strong></label><br>
		<input type="url" id="pp_enlace" name="pp_enlace" class="widefat"
			value="<?php echo esc_attr( $enlace ); ?>" placeholder="https://...">
	</p>
	<p>
		<label for="pp_tecnologias"><strong>Tecnologías</strong></label><br>
		<input type="text" id="pp_tecnologias" name="pp_tecnologias" class="widefat"
			value="<?php echo esc_attr( $tecnologias ); ?>" placeholder="PHP, MySQL, CSS">
		<span class="description">Separadas por comas.</span>
	</p>
	<?php
}

function pp_guardar_meta_box( $post_id ) {
	// 1. ¿Viene de nuestro formulario y el nonce es válido?
	if ( ! isset( $_POST['pp_nonce'] ) || ! wp_verify_nonce( $_POST['pp_nonce'], 'pp_guardar_datos' ) ) {
		return;
	}
	// 2. Los autoguardados de WordPress no deben tocar estos campos.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	// 3. ¿El usuario tiene permiso para editar este proyecto?
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// 4. Limpiamos (sanitize) antes de guardar en la base de datos.
	if ( isset( $_POST['pp_enlace'] ) ) {
		update_post_meta( $post_id, '_pp_enlace', esc_url_raw( wp_unslash( $_POST['pp_enlace'] ) ) );
	}
	if ( isset( $_POST['pp_tecnologias'] ) ) {
		update_post_meta( $post_id, '_pp_tecnologias', sanitize_text_field( wp_unslash( $_POST['pp_tecnologias'] ) ) );
	}
}
add_action( 'save_post_proyecto', 'pp_guardar_meta_box' );

/* =========================================================================
 * 3. PÁGINA DE AJUSTES (Ajustes > Proyectos Portfolio)
 *    Usa la Settings API de WordPress, que se encarga de guardar
 *    las opciones y de la seguridad del formulario.
 * ========================================================================= */
function pp_opciones_por_defecto() {
	return array(
		'numero'           => 6,
		'mostrar_tecnologias' => 1,
		'texto_boton'      => 'Ver proyecto',
	);
}

// Lee las opciones guardadas y rellena lo que falte con los valores por defecto.
function pp_opciones() {
	return wp_parse_args( get_option( 'pp_ajustes', array() ), pp_opciones_por_defecto() );
}

function pp_anadir_pagina_ajustes() {
	add_options_page(
		'Proyectos Portfolio',     // Título de la pestaña del navegador.
		'Proyectos Portfolio',     // Texto del menú.
		'manage_options',          // Solo administradores.
		'proyectos-portfolio',     // Slug de la página.
		'pp_pintar_pagina_ajustes'
	);
}
add_action( 'admin_menu', 'pp_anadir_pagina_ajustes' );

function pp_registrar_ajustes() {
	register_setting( 'pp_grupo', 'pp_ajustes', array(
		'sanitize_callback' => 'pp_limpiar_ajustes',
	) );

	add_settings_section( 'pp_seccion', 'Cómo se muestran los proyectos', '__return_false', 'proyectos-portfolio' );

	add_settings_field( 'numero', 'Número de proyectos', 'pp_campo_numero', 'proyectos-portfolio', 'pp_seccion' );
	add_settings_field( 'mostrar_tecnologias', 'Mostrar tecnologías', 'pp_campo_tecnologias', 'proyectos-portfolio', 'pp_seccion' );
	add_settings_field( 'texto_boton', 'Texto del botón', 'pp_campo_boton', 'proyectos-portfolio', 'pp_seccion' );
}
add_action( 'admin_init', 'pp_registrar_ajustes' );

// Valida lo que llega del formulario de ajustes antes de guardarlo.
function pp_limpiar_ajustes( $entrada ) {
	$por_defecto = pp_opciones_por_defecto();
	return array(
		'numero'              => isset( $entrada['numero'] ) ? max( 1, min( 50, absint( $entrada['numero'] ) ) ) : $por_defecto['numero'],
		'mostrar_tecnologias' => empty( $entrada['mostrar_tecnologias'] ) ? 0 : 1,
		'texto_boton'         => isset( $entrada['texto_boton'] ) && '' !== trim( $entrada['texto_boton'] )
			? sanitize_text_field( $entrada['texto_boton'] )
			: $por_defecto['texto_boton'],
	);
}

function pp_campo_numero() {
	$o = pp_opciones();
	printf( '<input type="number" name="pp_ajustes[numero]" value="%d" min="1" max="50">', (int) $o['numero'] );
}

function pp_campo_tecnologias() {
	$o = pp_opciones();
	printf(
		'<label><input type="checkbox" name="pp_ajustes[mostrar_tecnologias]" value="1" %s> Sí, mostrar las etiquetas</label>',
		checked( 1, $o['mostrar_tecnologias'], false )
	);
}

function pp_campo_boton() {
	$o = pp_opciones();
	printf( '<input type="text" name="pp_ajustes[texto_boton]" value="%s" class="regular-text">', esc_attr( $o['texto_boton'] ) );
}

function pp_pintar_pagina_ajustes() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1>Proyectos Portfolio</h1>
		<p>Para mostrar tus proyectos, pega <code>[proyectos]</code> en cualquier página.
		También puedes cambiar el número solo en esa página: <code>[proyectos numero="3"]</code>.</p>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'pp_grupo' );          // Campos ocultos + nonce.
			do_settings_sections( 'proyectos-portfolio' );
			submit_button( 'Guardar ajustes' );
			?>
		</form>
	</div>
	<?php
}

/* =========================================================================
 * 4. SHORTCODE [proyectos]
 *    Pega [proyectos] en una página y salen las tarjetas de proyectos.
 * ========================================================================= */
function pp_shortcode_proyectos( $atts ) {
	$o = pp_opciones();

	// Atributos del shortcode. Si no se indican, se usan los ajustes.
	$atts = shortcode_atts( array(
		'numero' => $o['numero'],
	), $atts, 'proyectos' );

	$consulta = new WP_Query( array(
		'post_type'      => 'proyecto',
		'posts_per_page' => max( 1, absint( $atts['numero'] ) ),
		'post_status'    => 'publish',
	) );

	if ( ! $consulta->have_posts() ) {
		return '<p>Todavía no hay proyectos publicados.</p>';
	}

	// Cargamos el CSS solo en las páginas que usan el shortcode.
	wp_enqueue_style( 'pp-estilos', PP_URL . 'assets/proyectos.css', array(), PP_VERSION );

	// ob_start() guarda el HTML en memoria en vez de imprimirlo,
	// porque un shortcode debe DEVOLVER el HTML, no hacer echo.
	ob_start();
	echo '<div class="pp-grid">';

	while ( $consulta->have_posts() ) {
		$consulta->the_post();
		$enlace      = get_post_meta( get_the_ID(), '_pp_enlace', true );
		$tecnologias = get_post_meta( get_the_ID(), '_pp_tecnologias', true );
		?>
		<article class="pp-card">
			<?php if ( has_post_thumbnail() ) : ?>
				<div class="pp-card__img"><?php the_post_thumbnail( 'medium_large' ); ?></div>
			<?php endif; ?>

			<div class="pp-card__body">
				<h3 class="pp-card__titulo"><?php the_title(); ?></h3>
				<div class="pp-card__texto"><?php the_excerpt(); ?></div>

				<?php if ( $o['mostrar_tecnologias'] && $tecnologias ) : ?>
					<ul class="pp-tags">
						<?php foreach ( array_filter( array_map( 'trim', explode( ',', $tecnologias ) ) ) as $tec ) : ?>
							<li><?php echo esc_html( $tec ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<a class="pp-card__boton" href="<?php echo esc_url( $enlace ? $enlace : get_permalink() ); ?>">
					<?php echo esc_html( $o['texto_boton'] ); ?>
				</a>
			</div>
		</article>
		<?php
	}

	echo '</div>';

	// Muy importante: dejar la consulta principal de WordPress como estaba.
	wp_reset_postdata();

	return ob_get_clean();
}
add_shortcode( 'proyectos', 'pp_shortcode_proyectos' );

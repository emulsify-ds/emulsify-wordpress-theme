<?php
/**
 * Registers optional ACF blocks rendered by Timber.
 *
 * @package Emulsify
 */

namespace Emulsify\Theme\Blocks;

use Emulsify\Theme\Support\AssetManifest;
use Emulsify\Theme\Support\FileDiscovery;

/**
 * ACF/Twig block integration.
 */
final class AcfBlocks {

	/**
	 * Component locator.
	 *
	 * @var ComponentLocator
	 */
	private $components;

	/**
	 * Duplicate ACF block registrations skipped by this registrar.
	 *
	 * @var array
	 */
	private $skipped_duplicates = array();

	/**
	 * Constructor.
	 *
	 * @param ComponentLocator|null $components Component locator.
	 */
	public function __construct( ?ComponentLocator $components = null ) {
		$this->components = $components ?? new ComponentLocator();
	}

	/**
	 * Registers optional ACF hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! function_exists( 'acf_register_block_type' ) ) {
			// ACF block registration is optional; no hooks are registered when ACF is
			// unavailable so non-ACF projects keep the same runtime behavior.
			return;
		}

		add_action( 'acf/init', array( $this, 'register_blocks' ) );
	}

	/**
	 * Registers component metadata as ACF blocks.
	 *
	 * @return void
	 */
	public function register_blocks(): void {
		if ( ! function_exists( 'acf_register_block_type' ) ) {
			return;
		}

		$seen_names = array();

		foreach ( $this->components->acf_components() as $component ) {
			$metadata = $this->metadata( $component['metadata_path'] );

			if ( null === $metadata ) {
				continue;
			}

			/**
			 * Filters ACF/Twig component metadata before block arguments are built.
			 *
			 * Metadata is read from the component *.component.json file. Defaults
			 * such as name, title, and render_callback are merged after this filter.
			 *
			 * @param array $metadata  Component metadata.
			 * @param array $component Component discovery record.
			 */
			$filtered_metadata = apply_filters( 'emulsify_theme_acf_block_metadata', $metadata, $component );

			if ( is_array( $filtered_metadata ) ) {
				$metadata = $filtered_metadata;
			}

			$args = $this->block_args( $component, $metadata );

			/**
			 * Filters final ACF block registration arguments.
			 *
			 * Duplicate block names are checked after this filter so child themes
			 * and project plugins can intentionally alter the final ACF block name.
			 *
			 * @param array $args      ACF block registration arguments.
			 * @param array $component Component discovery record.
			 * @param array $metadata  Filtered component metadata.
			 */
			$filtered_args = apply_filters( 'emulsify_theme_acf_block_args', $args, $component, $metadata );

			if ( is_array( $filtered_args ) ) {
				$args = $filtered_args;
			}

			$args['name'] = $this->normalize_block_name(
				$args['name'] ?? '',
				isset( $component['slug'] ) ? (string) $component['slug'] : 'block'
			);
			$args         = $this->with_asset_records( $args, $component, $metadata );
			$name         = $this->block_name( $args );

			if ( '' !== $name && isset( $seen_names[ $name ] ) ) {
				// Duplicate names can happen after filters alter metadata. Register
				// the first child-first record and report the skipped one in debug.
				$this->record_skipped_duplicate(
					'acf_block_name',
					$name,
					$seen_names[ $name ],
					$this->component_record( $component, $args ),
					'Duplicate ACF block name after metadata defaults were merged.'
				);
				continue;
			}

			if ( '' !== $name && $this->acf_block_registered( $name ) ) {
				$this->record_skipped_duplicate(
					'acf_registered_block_name',
					$name,
					array(
						'name'   => $name,
						'source' => 'existing',
					),
					$this->component_record( $component, $args ),
					'ACF block name is already registered.'
				);
				continue;
			}

			if ( '' !== $name ) {
				$seen_names[ $name ] = $this->component_record( $component, $args );
			}

			acf_register_block_type( $args );
		}

		$this->debug_skipped_duplicates(
			array_merge(
				$this->components->skipped_duplicates( 'acf' ),
				$this->skipped_duplicates
			)
		);
	}

	/**
	 * Gets duplicate ACF block records skipped by this registrar.
	 *
	 * @return array Skipped duplicate records.
	 */
	public function skipped_duplicates(): array {
		return $this->skipped_duplicates;
	}

	/**
	 * Renders an ACF block with Timber.
	 *
	 * @param array  $block      Block settings and attributes.
	 * @param string $content    Inner block content.
	 * @param bool   $is_preview TRUE during editor preview render.
	 * @param mixed  $post_id    Current post ID.
	 * @return void
	 */
	public function render_block( $block, $content = '', $is_preview = false, $post_id = 0 ): void {
		if ( ! is_array( $block ) ) {
			$this->render_error( __( 'Block rendering requires valid block metadata.', 'emulsify' ) );
			return;
		}

		if ( ! class_exists( '\Timber\Timber' ) ) {
			$this->render_error( __( 'Block rendering requires Timber.', 'emulsify' ) );
			return;
		}

		$template = $this->template( $block );

		if ( '' === $template ) {
			$this->render_error( __( 'Block rendering error: no Twig template defined.', 'emulsify' ) );
			return;
		}

		$context               = \Timber\Timber::context();
		$context['block']      = $block;
		$context['content']    = is_scalar( $content ) ? (string) $content : '';
		$context['fields']     = $this->fields( $post_id );
		$context['is_preview'] = (bool) $is_preview;

		try {
			// ACF expects render_callback output directly. Timber::render echoes the
			// template, matching ACF's callback contract.
			\Timber\Timber::render( $template, $context );
		} catch ( \Throwable $throwable ) {
			$this->render_error( $throwable->getMessage() );
		}
	}

	/**
	 * Reads component metadata.
	 *
	 * @param string $path Absolute metadata path.
	 * @return array|null Component metadata, or null when invalid.
	 */
	private function metadata( string $path ): ?array {
		$contents = file_get_contents( $path );

		if ( ! is_string( $contents ) ) {
			return null;
		}

		$metadata = json_decode( $contents, true );

		return is_array( $metadata ) && JSON_ERROR_NONE === json_last_error() ? $metadata : null;
	}

	/**
	 * Builds ACF block registration arguments.
	 *
	 * @param array $component Component record.
	 * @param array $metadata  Component metadata.
	 * @return array ACF block arguments.
	 */
	private function block_args( array $component, array $metadata ): array {
		$defaults = array(
			'name'  => 'emulsify-' . $component['slug'],
			'title' => ucwords( str_replace( '-', ' ', $component['slug'] ) ),
			'mode'  => 'preview',
		);

		$args                    = array_merge( $defaults, $metadata );
		$args['name']            = $this->normalize_block_name( $args['name'] ?? '', $component['slug'] );
		$args['render_callback'] = array( $this, 'render_block' );
		$args['twig_template']   = $component['template'];
		$args['data']            = isset( $args['data'] ) && is_array( $args['data'] ) ? $args['data'] : array();
		$args['data']['twig_template'] = $component['template'];

		return $args;
	}

	/**
	 * Adds a scoped asset callback when a component declares block assets.
	 *
	 * @param array $args      ACF block registration arguments.
	 * @param array $component Component record.
	 * @param array $metadata  Component metadata.
	 * @return array ACF block registration arguments.
	 */
	private function with_asset_records( array $args, array $component, array $metadata ): array {
		$asset_records = $this->block_asset_records( $component, $metadata, $args );

		/**
		 * Filters scoped ACF/Twig block asset records before registration.
		 *
		 * Records are grouped by frontend/editor context and css/js type. Return
		 * an empty record set to leave the block without scoped asset enqueueing.
		 *
		 * @param array $asset_records Scoped asset records.
		 * @param array $component     Component discovery record.
		 * @param array $metadata      Component metadata.
		 * @param array $args          ACF block registration arguments.
		 */
		$filtered = apply_filters( 'emulsify_theme_acf_block_asset_records', $asset_records, $component, $metadata, $args );

		if ( is_array( $filtered ) ) {
			$asset_records = $this->normalize_asset_context_records( $filtered );
		}

		if ( ! $this->has_asset_records( $asset_records ) ) {
			return $args;
		}

		$existing_callback     = isset( $args['enqueue_assets'] ) && is_callable( $args['enqueue_assets'] ) ? $args['enqueue_assets'] : null;
		$args['enqueue_assets'] = $this->enqueue_assets_callback( $asset_records, $existing_callback, $this->block_name( $args ) );

		return $args;
	}

	/**
	 * Gets scoped block asset records.
	 *
	 * @param array $component Component record.
	 * @param array $metadata  Component metadata.
	 * @param array $args      ACF block registration arguments.
	 * @return array Scoped asset records.
	 */
	private function block_asset_records( array $component, array $metadata, array $args ): array {
		$manifest_records = ( new AssetManifest() )->scoped_asset_records(
			$this->asset_identifiers( $component, $metadata, $args )
		);

		if ( is_array( $manifest_records ) ) {
			return $this->normalize_asset_context_records( $manifest_records );
		}

		$metadata_records = $this->metadata_asset_records( $component, $metadata );

		return is_array( $metadata_records ) ? $metadata_records : $this->empty_asset_context_records();
	}

	/**
	 * Gets identifiers used to match manifest scoped assets.
	 *
	 * @param array $component Component record.
	 * @param array $metadata  Component metadata.
	 * @param array $args      ACF block registration arguments.
	 * @return array Asset identifiers.
	 */
	private function asset_identifiers( array $component, array $metadata, array $args ): array {
		$identifiers = array(
			$args['name'] ?? '',
			'acf/' . ( $args['name'] ?? '' ),
			$component['relative'] ?? '',
			$component['slug'] ?? '',
		);

		if ( isset( $metadata['name'] ) && is_scalar( $metadata['name'] ) ) {
			$metadata_name = trim( (string) $metadata['name'] );
			$normalized    = $this->normalize_block_name( $metadata_name, isset( $component['slug'] ) ? (string) $component['slug'] : 'block' );
			$identifiers[] = $metadata_name;
			$identifiers[] = $normalized;
			$identifiers[] = 'acf/' . $normalized;
		}

		return array_values(
			array_unique(
				array_filter(
					$identifiers,
					static function ( $identifier ): bool {
						return is_scalar( $identifier ) && '' !== trim( (string) $identifier );
					}
				)
			)
		);
	}

	/**
	 * Gets component metadata asset records.
	 *
	 * @param array $component Component record.
	 * @param array $metadata  Component metadata.
	 * @return array|null Scoped asset records, or null when undeclared.
	 */
	private function metadata_asset_records( array $component, array $metadata ): ?array {
		if ( empty( $metadata['assets'] ) || ! is_array( $metadata['assets'] ) ) {
			return null;
		}

		$base_path = ! empty( $component['path'] ) && is_scalar( $component['path'] )
			? rtrim( (string) $component['path'], '/\\' )
			: dirname( (string) ( $component['metadata_path'] ?? '' ) );
		$base_uri  = $this->component_base_uri( $component, $base_path );

		if ( '' === $base_uri || '' === $base_path ) {
			return $this->empty_asset_context_records();
		}

		return $this->metadata_context_records( $metadata['assets'], $base_path, $base_uri, $component );
	}

	/**
	 * Gets frontend/editor records from component metadata.
	 *
	 * @param array  $assets    Component metadata assets.
	 * @param string $base_path Component base path.
	 * @param string $base_uri  Component base URI.
	 * @param array  $component Component record.
	 * @return array Scoped asset records.
	 */
	private function metadata_context_records( array $assets, string $base_path, string $base_uri, array $component ): array {
		$records = $this->empty_asset_context_records();

		if ( isset( $assets['frontend'] ) || isset( $assets['editor'] ) ) {
			if ( isset( $assets['frontend'] ) && is_array( $assets['frontend'] ) ) {
				$records['frontend'] = $this->metadata_type_records( $assets['frontend'], $base_path, $base_uri, $component );
			}

			if ( isset( $assets['editor'] ) && is_array( $assets['editor'] ) ) {
				$records['editor'] = $this->metadata_type_records( $assets['editor'], $base_path, $base_uri, $component );
			}

			return $records;
		}

		$records['frontend'] = $this->metadata_type_records( $assets, $base_path, $base_uri, $component );

		return $records;
	}

	/**
	 * Gets css/js records from component metadata.
	 *
	 * @param array  $assets    Metadata asset section.
	 * @param string $base_path Component base path.
	 * @param string $base_uri  Component base URI.
	 * @param array  $component Component record.
	 * @return array Asset records grouped by type.
	 */
	private function metadata_type_records( array $assets, string $base_path, string $base_uri, array $component ): array {
		$records = array(
			'css' => array(),
			'js'  => array(),
		);

		foreach ( array( 'css', 'js' ) as $type ) {
			if ( empty( $assets[ $type ] ) || ! is_array( $assets[ $type ] ) ) {
				continue;
			}

			foreach ( $assets[ $type ] as $entry ) {
				$record = $this->metadata_asset_record( $entry, $type, $base_path, $base_uri, $component );

				if ( null !== $record ) {
					$records[ $type ][] = $record;
				}
			}
		}

		$records['css'] = FileDiscovery::sort_by_priority_and_relative( $records['css'] );
		$records['js']  = FileDiscovery::sort_by_priority_and_relative( $records['js'] );

		return $records;
	}

	/**
	 * Normalizes one component metadata asset record.
	 *
	 * @param mixed  $entry     Metadata asset entry.
	 * @param string $type      Asset type.
	 * @param string $base_path Component base path.
	 * @param string $base_uri  Component base URI.
	 * @param array  $component Component record.
	 * @return array|null Asset record, or null when invalid.
	 */
	private function metadata_asset_record( $entry, string $type, string $base_path, string $base_uri, array $component ): ?array {
		$data     = is_array( $entry ) ? $entry : array( 'path' => $entry );
		$relative = $this->entry_path( $data );

		if ( '' === $relative || strtolower( pathinfo( $relative, PATHINFO_EXTENSION ) ) !== $type ) {
			return null;
		}

		$path = $base_path . '/' . $relative;

		if ( ! is_readable( $path ) ) {
			return null;
		}

		return array(
			'path'         => $path,
			'priority'     => 0,
			'relative'     => $this->entry_relative( $data, $relative ),
			'uri'          => rtrim( $base_uri, '/' ) . '/' . $relative,
			'version'      => $this->entry_version( $data, $path ),
			'dependencies' => $this->entry_dependencies( $data ),
			'module'       => $this->entry_module( $data ),
			'source'       => isset( $component['source'] ) ? (string) $component['source'] : '',
		);
	}

	/**
	 * Builds a component base URI from discovery metadata.
	 *
	 * @param array  $component Component record.
	 * @param string $base_path Component base path.
	 * @return string Component base URI, or empty string when unavailable.
	 */
	private function component_base_uri( array $component, string $base_path ): string {
		if ( empty( $component['root_uri'] ) || empty( $component['root_path'] ) ) {
			return '';
		}

		$relative = FileDiscovery::relative_path( (string) $component['root_path'], $base_path );

		return rtrim( (string) $component['root_uri'], '/' ) . ( '' === $relative ? '' : '/' . $relative );
	}

	/**
	 * Builds the ACF enqueue callback for scoped block assets.
	 *
	 * @param array         $asset_records     Scoped asset records.
	 * @param callable|null $existing_callback Existing enqueue callback.
	 * @param string        $block_name        ACF block name.
	 * @return callable Enqueue callback.
	 */
	private function enqueue_assets_callback( array $asset_records, ?callable $existing_callback, string $block_name ): callable {
		return function ( ...$callback_args ) use ( $asset_records, $existing_callback, $block_name ): void {
			if ( is_callable( $existing_callback ) ) {
				call_user_func_array( $existing_callback, $callback_args );
			}

			$this->enqueue_asset_records( $asset_records['frontend'] ?? array(), $block_name, 'frontend' );

			if ( $this->is_editor_context() ) {
				$this->enqueue_asset_records( $asset_records['editor'] ?? array(), $block_name, 'editor' );
			}
		};
	}

	/**
	 * Enqueues a grouped asset record set.
	 *
	 * @param array  $records    Asset records grouped by css/js.
	 * @param string $block_name ACF block name.
	 * @param string $context    Asset context.
	 * @return void
	 */
	private function enqueue_asset_records( array $records, string $block_name, string $context ): void {
		foreach ( $records['css'] ?? array() as $record ) {
			$this->enqueue_style_record( $record, $block_name, $context );
		}

		foreach ( $records['js'] ?? array() as $record ) {
			$this->enqueue_script_record( $record, $block_name, $context );
		}
	}

	/**
	 * Enqueues a style record.
	 *
	 * @param array  $record     Asset record.
	 * @param string $block_name ACF block name.
	 * @param string $context    Asset context.
	 * @return void
	 */
	private function enqueue_style_record( array $record, string $block_name, string $context ): void {
		if ( empty( $record['uri'] ) || ! function_exists( 'wp_enqueue_style' ) ) {
			return;
		}

		wp_enqueue_style(
			$this->asset_handle( 'emulsify-acf-' . $context, $block_name, $record ),
			(string) $record['uri'],
			$this->asset_dependencies( $record ),
			$record['version'] ?? null
		);
	}

	/**
	 * Enqueues a script record.
	 *
	 * @param array  $record     Asset record.
	 * @param string $block_name ACF block name.
	 * @param string $context    Asset context.
	 * @return void
	 */
	private function enqueue_script_record( array $record, string $block_name, string $context ): void {
		if ( empty( $record['uri'] ) ) {
			return;
		}

		$handle = $this->asset_handle( 'emulsify-acf-' . $context, $block_name, $record );

		if ( $this->is_module_script( $record ) && function_exists( 'wp_enqueue_script_module' ) ) {
			wp_enqueue_script_module(
				$handle,
				(string) $record['uri'],
				$this->asset_dependencies( $record ),
				$record['version'] ?? null
			);
			return;
		}

		if ( ! function_exists( 'wp_enqueue_script' ) ) {
			return;
		}

		wp_enqueue_script(
			$handle,
			(string) $record['uri'],
			$this->asset_dependencies( $record ),
			$record['version'] ?? null,
			array(
				'in_footer' => true,
			)
		);
	}

	/**
	 * Builds a WordPress-safe scoped asset handle.
	 *
	 * @param string $prefix     Handle prefix.
	 * @param string $block_name ACF block name.
	 * @param array  $record     Asset record.
	 * @return string Asset handle.
	 */
	private function asset_handle( string $prefix, string $block_name, array $record ): string {
		if ( ! empty( $record['handle'] ) && is_scalar( $record['handle'] ) ) {
			return sanitize_key( (string) $record['handle'] );
		}

		$relative = isset( $record['relative'] ) && is_scalar( $record['relative'] ) ? (string) $record['relative'] : basename( (string) ( $record['path'] ?? 'asset' ) );
		$name     = preg_replace( '/\.(css|js)$/', '', $relative );
		$name     = preg_replace( '/[^A-Za-z0-9_-]+/', '-', (string) $name );

		return sanitize_key( $prefix . '-' . $block_name . '-' . trim( (string) $name, '-' ) );
	}

	/**
	 * Gets dependency handles from an asset record.
	 *
	 * @param array $record Asset record.
	 * @return array Dependency handles.
	 */
	private function asset_dependencies( array $record ): array {
		return isset( $record['dependencies'] ) && is_array( $record['dependencies'] ) ? $record['dependencies'] : array();
	}

	/**
	 * Checks whether a script should be enqueued as a module.
	 *
	 * @param array $record Asset record.
	 * @return bool TRUE when the script is a module.
	 */
	private function is_module_script( array $record ): bool {
		return ! array_key_exists( 'module', $record ) || null === $record['module'] ? true : (bool) $record['module'];
	}

	/**
	 * Checks whether the current request is an editor/admin context.
	 *
	 * @return bool TRUE when editor/admin-only assets should load.
	 */
	private function is_editor_context(): bool {
		return function_exists( 'is_admin' ) && is_admin();
	}

	/**
	 * Gets an empty scoped asset record set.
	 *
	 * @return array Empty records.
	 */
	private function empty_asset_context_records(): array {
		return array(
			'frontend' => array(
				'css' => array(),
				'js'  => array(),
			),
			'editor'   => array(
				'css' => array(),
				'js'  => array(),
			),
		);
	}

	/**
	 * Normalizes scoped asset record groups.
	 *
	 * @param array $records Candidate records.
	 * @return array Normalized records.
	 */
	private function normalize_asset_context_records( array $records ): array {
		$normalized = $this->empty_asset_context_records();

		foreach ( array( 'frontend', 'editor' ) as $context ) {
			if ( empty( $records[ $context ] ) || ! is_array( $records[ $context ] ) ) {
				continue;
			}

			foreach ( array( 'css', 'js' ) as $type ) {
				$normalized[ $context ][ $type ] = isset( $records[ $context ][ $type ] ) && is_array( $records[ $context ][ $type ] )
					? array_values( $records[ $context ][ $type ] )
					: array();
			}
		}

		return $normalized;
	}

	/**
	 * Checks whether any scoped asset records are present.
	 *
	 * @param array $records Scoped records.
	 * @return bool TRUE when records are present.
	 */
	private function has_asset_records( array $records ): bool {
		foreach ( array( 'frontend', 'editor' ) as $context ) {
			foreach ( array( 'css', 'js' ) as $type ) {
				if ( ! empty( $records[ $context ][ $type ] ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Gets an asset entry path.
	 *
	 * @param array $entry Asset entry.
	 * @return string Relative path.
	 */
	private function entry_path( array $entry ): string {
		foreach ( array( 'path', 'file', 'src', 'href' ) as $key ) {
			if ( isset( $entry[ $key ] ) && is_scalar( $entry[ $key ] ) ) {
				return $this->normalize_relative_asset_path( (string) $entry[ $key ] );
			}
		}

		return '';
	}

	/**
	 * Gets the record-relative path.
	 *
	 * @param array  $entry    Asset entry.
	 * @param string $fallback Fallback relative path.
	 * @return string Relative path.
	 */
	private function entry_relative( array $entry, string $fallback ): string {
		if ( isset( $entry['relative'] ) && is_scalar( $entry['relative'] ) ) {
			$relative = $this->normalize_relative_asset_path( (string) $entry['relative'] );

			if ( '' !== $relative ) {
				return $relative;
			}
		}

		return $fallback;
	}

	/**
	 * Gets the asset version.
	 *
	 * @param array  $entry Asset entry.
	 * @param string $path  Absolute asset path.
	 * @return string|null Version.
	 */
	private function entry_version( array $entry, string $path ): ?string {
		foreach ( array( 'version', 'hash' ) as $key ) {
			if ( isset( $entry[ $key ] ) && is_scalar( $entry[ $key ] ) && '' !== trim( (string) $entry[ $key ] ) ) {
				return (string) $entry[ $key ];
			}
		}

		$modified = filemtime( $path );

		return false === $modified ? null : (string) $modified;
	}

	/**
	 * Gets normalized dependency handles.
	 *
	 * @param array $entry Asset entry.
	 * @return array Dependency handles.
	 */
	private function entry_dependencies( array $entry ): array {
		$dependencies = $entry['dependencies'] ?? ( $entry['deps'] ?? array() );

		if ( ! is_array( $dependencies ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map(
					static function ( $dependency ) {
						return is_scalar( $dependency ) ? trim( (string) $dependency ) : null;
					},
					$dependencies
				),
				static function ( $dependency ): bool {
					return is_string( $dependency ) && '' !== $dependency;
				}
			)
		);
	}

	/**
	 * Gets an optional script module flag.
	 *
	 * @param array $entry Asset entry.
	 * @return bool|null Module flag, or null to use service default.
	 */
	private function entry_module( array $entry ): ?bool {
		if ( array_key_exists( 'module', $entry ) ) {
			return ! empty( $entry['module'] );
		}

		if ( array_key_exists( 'type', $entry ) && is_scalar( $entry['type'] ) ) {
			return 'module' === strtolower( trim( (string) $entry['type'] ) );
		}

		return null;
	}

	/**
	 * Normalizes a relative asset path.
	 *
	 * @param string $path Candidate path.
	 * @return string Safe relative path.
	 */
	private function normalize_relative_asset_path( string $path ): string {
		$path = trim( str_replace( '\\', '/', $path ) );

		if (
			'' === $path
			|| false !== strpos( $path, "\0" )
			|| 0 === strpos( $path, '/' )
			|| preg_match( '#(^|/)\.\.(/|$)#', $path )
		) {
			return '';
		}

		while ( 0 === strpos( $path, './' ) ) {
			$path = substr( $path, 2 );
		}

		return $path;
	}

	/**
	 * Normalizes an ACF PHP block name to an ACF-safe un-namespaced slug.
	 *
	 * ACF's PHP registration API expects names like "testimonial"; WordPress
	 * exposes the final editor block as "acf/testimonial".
	 *
	 * @param mixed  $name     Candidate ACF block name.
	 * @param string $fallback Component slug fallback.
	 * @return string ACF-safe block name.
	 */
	private function normalize_block_name( $name, string $fallback ): string {
		$candidate = is_scalar( $name ) ? (string) $name : '';

		if ( '' === trim( $candidate ) ) {
			$candidate = 'emulsify-' . $fallback;
		}

		$normalized = strtolower( trim( $candidate ) );
		$normalized = preg_replace( '/[^a-z0-9-]+/', '-', $normalized );
		$normalized = trim( (string) $normalized, '-' );

		if ( '' === $normalized ) {
			$normalized = 'emulsify-' . $fallback;
			$normalized = preg_replace( '/[^a-z0-9-]+/', '-', strtolower( $normalized ) );
			$normalized = trim( (string) $normalized, '-' );
		}

		if ( '' === $normalized || ! preg_match( '/^[a-z]/', $normalized ) ) {
			$normalized = 'emulsify-' . $normalized;
		}

		return $normalized;
	}

	/**
	 * Gets a final ACF block name from registration arguments.
	 *
	 * @param array $args ACF block registration arguments.
	 * @return string Block name, or an empty string when unavailable.
	 */
	private function block_name( array $args ): string {
		return ! empty( $args['name'] ) && is_string( $args['name'] ) ? trim( $args['name'] ) : '';
	}

	/**
	 * Checks whether ACF already knows about a block name.
	 *
	 * @param string $name ACF block name.
	 * @return bool TRUE when the block is already registered.
	 */
	private function acf_block_registered( string $name ): bool {
		return function_exists( 'acf_get_block_type' ) && is_array( acf_get_block_type( $name ) );
	}

	/**
	 * Builds a debug record for an ACF component registration.
	 *
	 * @param array $component Component record.
	 * @param array $args      ACF block registration arguments.
	 * @return array Debug record.
	 */
	private function component_record( array $component, array $args ): array {
		return array(
			'name'          => $this->block_name( $args ),
			'relative'      => isset( $component['relative'] ) ? $component['relative'] : '',
			'source'        => isset( $component['source'] ) ? $component['source'] : '',
			'metadata_path' => isset( $component['metadata_path'] ) ? $component['metadata_path'] : '',
			'template'      => isset( $component['template'] ) ? $component['template'] : '',
		);
	}

	/**
	 * Records a skipped duplicate ACF block.
	 *
	 * @param string $type    Duplicate type.
	 * @param string $name    Duplicate block name.
	 * @param array  $kept    Higher-priority record.
	 * @param array  $skipped Lower-priority skipped record.
	 * @param string $reason  Human-readable reason.
	 * @return void
	 */
	private function record_skipped_duplicate( string $type, string $name, array $kept, array $skipped, string $reason ): void {
		$this->skipped_duplicates[] = array(
			'type'    => $type,
			'name'    => $name,
			'reason'  => $reason,
			'kept'    => $kept,
			'skipped' => $skipped,
		);
	}

	/**
	 * Logs duplicate block records and optionally exposes admin notices.
	 *
	 * @param array $duplicates Duplicate records.
	 * @return void
	 */
	private function debug_skipped_duplicates( array $duplicates ): void {
		if ( empty( $duplicates ) || ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$messages = array();

		foreach ( $duplicates as $duplicate ) {
			$messages[] = $this->duplicate_message( $duplicate );
			error_log( '[Emulsify] ' . end( $messages ) );
		}

		$this->admin_notice( $messages );
	}

	/**
	 * Adds an admin-only notice for skipped duplicate blocks.
	 *
	 * @param array $messages Notice messages.
	 * @return void
	 */
	private function admin_notice( array $messages ): void {
		if ( empty( $messages ) || ! function_exists( 'add_action' ) || ! function_exists( 'is_admin' ) || ! is_admin() ) {
			return;
		}

		add_action(
			'admin_notices',
			static function () use ( $messages ): void {
				if ( function_exists( 'current_user_can' ) && ! current_user_can( 'edit_theme_options' ) ) {
					return;
				}

				echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Emulsify skipped duplicate ACF block definitions.', 'emulsify' ) . '</strong></p><ul>';

				foreach ( $messages as $message ) {
					echo '<li>' . esc_html( $message ) . '</li>';
				}

				echo '</ul></div>';
			}
		);
	}

	/**
	 * Formats a duplicate debug message.
	 *
	 * @param array $duplicate Duplicate record.
	 * @return string Debug message.
	 */
	private function duplicate_message( array $duplicate ): string {
		$kept    = isset( $duplicate['kept']['metadata_path'] ) ? $duplicate['kept']['metadata_path'] : ( $duplicate['kept']['name'] ?? 'unknown' );
		$skipped = isset( $duplicate['skipped']['metadata_path'] ) ? $duplicate['skipped']['metadata_path'] : ( $duplicate['skipped']['name'] ?? 'unknown' );

		return sprintf(
			'%s "%s" skipped %s in favor of %s.',
			isset( $duplicate['reason'] ) ? $duplicate['reason'] : 'Duplicate block definition.',
			isset( $duplicate['name'] ) ? $duplicate['name'] : 'unknown',
			$skipped,
			$kept
		);
	}

	/**
	 * Gets ACF fields when ACF is active.
	 *
	 * @param mixed $post_id Current post ID.
	 * @return array ACF field values.
	 */
	private function fields( $post_id ): array {
		if ( ! function_exists( 'get_fields' ) ) {
			return array();
		}

		$fields = get_fields( $post_id );

		return is_array( $fields ) ? $fields : array();
	}

	/**
	 * Gets the block Twig template.
	 *
	 * @param array $block Block settings and attributes.
	 * @return string Template path.
	 */
	private function template( array $block ): string {
		if ( ! empty( $block['data']['twig_template'] ) && is_string( $block['data']['twig_template'] ) ) {
			return $block['data']['twig_template'];
		}

		if ( ! empty( $block['twig_template'] ) && is_string( $block['twig_template'] ) ) {
			return $block['twig_template'];
		}

		return '';
	}

	/**
	 * Renders an editor-only block error.
	 *
	 * @param string $message Error message.
	 * @return void
	 */
	private function render_error( string $message ): void {
		if ( function_exists( 'current_user_can' ) && ! current_user_can( 'edit_posts' ) ) {
			// Block errors are authoring diagnostics. Avoid showing implementation
			// details to normal frontend visitors.
			return;
		}

		printf(
			'<p><strong>%s</strong> %s</p>',
			esc_html__( 'Emulsify block error:', 'emulsify' ),
			esc_html( $message )
		);
	}
}

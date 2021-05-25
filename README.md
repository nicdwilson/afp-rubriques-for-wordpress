# AFP Rubriques

A WordPress plugin written for The Citizen, https://citizen.co.za, to facilitate the import of automated rubriques from Agence France Presses

## Filters

There are two available filters.

#### Filters for image styling

The following filter is available for use to customize the styling of images placed as static links in imported article bodies.

`add_filter( 'afp_rubrique_image_styling', $callback, $priority, $class_list );`

Example

```PHP
add_filter( 'afp_rubrique_image_styling', 'afp_rubriques_apply_citizen_image_classes', 1, 1 );

function afp_rubriques_apply_citizen_image_classes( $class_list ) {

$class_list = array(
		'div' => 'single-img',
		'img' => 'img-responsive',
		'figcaption' => 'caption'
	);
	
	return $class_list;
}
```

Default image styling uses WordPress 5 markup

```$html
<div class="wp-block-image">
    <figure class="aligncenter"
        <img>
        <figcaption> Caption text </figcaption>
    </figure>
</div>
```

### Filter for post data

The following filter is applied before `wp_post_insert` is called when importing a new post.

It passes the post data array, allowing plugin users to manipulate the post data before it is inserted.

`$post_data = apply_filters( 'afp_rubriques_pre_post_import', $post_data );`

## Actions

There is on available action hook

This passes the post ID after the post is imported, categories are applied, meta data is added and images are processed.

`do_action( 'afp_rubriques_post_imported', $post_id );`

## Changelog

v1.1 21 March 2020
- Changed delivery path check to differentiate between missing/invalid path and missing AFP delivery directory tree
v1.0.1 21 April 2019
- Improved error handling for empty xml objects
- Added filesize() check before xml object creation
v1.0.2 22 April 2019
- Removed bug which caused loop to stall on corrupt files

<?php
/**
 * Created by PhpStorm.
 * User: nicdw
 * Date: 10/24/2018
 * Time: 9:50 AM
 */

namespace AFP;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class File_Reader extends Configuration {

	/**
	 * Returns the first paragraph of the story for use in post_excerpt field
	 *
	 * @param object $xml
	 *
	 * @return string
	 */
	public function get_the_excerpt( $xml = object ): string {

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		return (string) $xml->NewsItem->NewsComponent->NewsComponent[0]->ContentItem->DataContent->p[0];
	}

	/**
	 * Content of the story for post_content and readies the image data for processing.
	 *
	 * @param object $xml
	 * @param string $image_url
	 *
	 * @return mixed
	 */
	public function get_the_content( $xml = object, $image_url = '' ): array {

		/*
		 * Remove first paragraph if options are set to require this. Prevents excerpt from duplicating
		 * first paragraph of story.
		 */
		if ( true === $this->settings['first_paragraph'] ) {

			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			unset( $xml->NewsItem->NewsComponent->NewsComponent[0]->ContentItem->DataContent->p[0] );
		}

		/*
		 * Allow users to style their own images
		 */
		$image_classes = apply_filters( 'afp_rubrique_image_styling', array() );
		$image_classes = $this->check_image_styling( $image_classes );

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$xml     = $xml->NewsItem->NewsComponent->NewsComponent[0]->ContentItem->DataContent;
		$content = '';
		$images  = array();
		$i       = 0;

		foreach ( $xml->children() as $element ) {

			switch ( $element->getName() ) {

				case 'media':
					if ( strtolower( (string) $element->attributes()->{'media-type'} ) === 'image' ) {

						$image_tag = $element->{'media-reference'}->attributes()->{'data-location'};

						$image_tag = str_replace( '#', '', $image_tag );

						$media = $xml->xpath( '//NewsItem/NewsComponent/NewsComponent[@Duid="' . $image_tag . '"]' );

						$caption = (string) $media[0]->NewsLines->HeadLine;
						$credit  = (string) $media[0]->NewsLines->CreditLine;
						$byline  = (string) $media[0]->NewsLines->ByLine;
						if ( ! empty( $credit ) ) {
							$caption .= '. ' . $credit;
						}
						if ( ! empty( $credit ) ) {
							$caption .= '/' . $byline;
						}

						$file = $media[0]->xpath( '//NewsComponent[@Duid="' . $image_tag . '"]/NewsComponent/Role[@FormalName="HighDef"]/following-sibling::ContentItem' );
						$file = (string) $file[0]->attributes()->Href;

						$figure = $element->addChild( 'figure', '' );

						if( !empty( $image_classes['figure'] ) ) {
							$figure->addAttribute( 'class', $image_classes['figure'] );
						}

						$image = $figure->addChild( 'img', '' );
						$image->addAttribute( 'src', $image_url . $file );
						$image->addAttribute( 'alt', $caption );

						if( !empty( $image_classes['img'] ) ) {
							$image->addAttribute( 'class', $image_classes['img'] );
						}

						$image_caption = $figure->addChild( 'figcaption', $caption );

						if( !empty( $image_classes['figcaption'] ) ) {
							$image_caption->addAttribute( 'class', $image_classes['figcaption'] );
						}

						$images[ $i ]['caption'] = $caption;
						$images[ $i ]['file']    = $file;

						if ( 'photo0' === $image_tag && 'true' ===  $this->settings['image_zero_in_body'] ) {

							$content .= '<!-- wp:image {"sizeSlug":"large","linkDestination":"none" "className":"' . $image_classes['block'] . '"} -->';
							$content .= $figure->asXML();
							$content .= '<!-- /wp:image -->';

						}

						if( 'photo0' !== $image_tag && 'true' === $this->settings['use_all_images'] ) {

							$content .= '<!-- wp:image {"sizeSlug":"large","linkDestination":"none" "className":"' . $image_classes['block'] . '"} -->';
							$content .= $figure->asXML();
							$content .= '<!-- /wp:image -->';
						}

						$i ++;

					}

					break;
				case 'p':
					$content .= '<!-- wp:paragraph -->';
					$content .= $element->asXML();
					$content .= '<!-- /wp:paragraph -->';
					break;
				default:
					$content .= $element->asXML();
					break;
			}
		}

		$post_content['content'] = $content;
		$post_content['images']  = $images;

		return $post_content;

	}

	/**
	 * Returns the title of a story
	 *
	 * @param object $xml
	 *
	 * @return string
	 */
	public function get_the_title( $xml = object ): string {

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		return (string) $xml->NewsItem->NewsComponent->NewsLines->HeadLine;
	}

	/**
	 * Returns th published date in the correct format
	 *
	 * @param object $xml
	 *
	 * @return false|string
	 */
	public function get_the_published_date( $xml = object ) {

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$date = (string) ( $xml->NewsItem->NewsManagement->FirstCreated );
		$date = gmdate( 'Y-m-d H:i:s', strtotime( $date ) );

		return $date;
	}

	/**
	 * Returns the modified date in the correct format
	 *
	 * @param object $xml
	 *
	 * @return false|string
	 */
	public function get_the_modified_date( $xml = object ) {

		// phpcs:ignore
		$date = (string) $xml->NewsItem->NewsManagement->ThisRevisionCreated;
		$date = gmdate( 'Y-m-d H:i:s', strtotime( $date ) );

		return $date;

	}

	/**
	 * Returns the byline of the story
	 *
	 * @param object $xml
	 *
	 * @return string
	 */
	public function get_the_byline( $xml = object ): string {

		// phpcs:ignore
		$byline = (string) $xml->NewsItem->NewsComponent->NewsLines->ByLine;

		if ( empty( $byline ) ) {
			$byline = 'AFP';
		} else {
			$byline = $byline . '/AFP';
		}

		return $byline;
	}

}

<?php
/**
 * Tests for ReferenceRewriter.
 *
 * @package SiteCargo
 */

declare( strict_types=1 );

namespace SiteCargo\Tests;

use SiteCargo\Engine\ReferenceRewriter;
use WP_UnitTestCase;

/**
 * @covers \SiteCargo\Engine\ReferenceRewriter
 */
final class ReferenceRewriterTest extends WP_UnitTestCase {

	/**
	 * @var ReferenceRewriter
	 */
	private $rewriter;

	public function set_up(): void {
		parent::set_up();
		$this->rewriter = new ReferenceRewriter();
	}

	public function test_extracts_media_id_from_image_block(): void {
		$content = '<!-- wp:image {"id":42,"sizeSlug":"large"} --><figure class="wp-block-image"><img src="x.jpg" class="wp-image-42"/></figure><!-- /wp:image -->';

		$references = $this->rewriter->extract( $content );

		$this->assertCount( 1, $references );
		$this->assertSame( 'media', $references[0]['kind'] );
		$this->assertSame( 42, $references[0]['original_id'] );
		$this->assertSame( 'id', $references[0]['attr'] );
		$this->assertSame( array( 0 ), $references[0]['path'] );
	}

	public function test_extracts_pattern_reference_from_block(): void {
		$references = $this->rewriter->extract( '<!-- wp:block {"ref":99} /-->' );

		$this->assertCount( 1, $references );
		$this->assertSame( 'pattern', $references[0]['kind'] );
		$this->assertSame( 99, $references[0]['original_id'] );
	}

	public function test_extracts_nested_media_reference_with_full_path(): void {
		$content = '<!-- wp:group --><div class="wp-block-group">'
			. '<!-- wp:columns --><div class="wp-block-columns">'
			. '<!-- wp:column --><div class="wp-block-column">'
			. '<!-- wp:image {"id":7} --><figure class="wp-block-image"><img class="wp-image-7"/></figure><!-- /wp:image -->'
			. '</div><!-- /wp:column -->'
			. '</div><!-- /wp:columns -->'
			. '</div><!-- /wp:group -->';

		$references = $this->rewriter->extract( $content );

		$this->assertCount( 1, $references );
		$this->assertSame( 7, $references[0]['original_id'] );
		// group(0) > columns(0) > column(0) > image(0).
		$this->assertSame( array( 0, 0, 0, 0 ), $references[0]['path'] );
	}

	public function test_extracts_legacy_gallery_id_list(): void {
		$content = '<!-- wp:gallery {"ids":[1,2,3]} --><figure class="wp-block-gallery"></figure><!-- /wp:gallery -->';

		$references = $this->rewriter->extract( $content );

		$this->assertCount( 3, $references );
		$this->assertSame( 1, $references[0]['original_id'] );
		$this->assertSame( 0, $references[0]['index'] );
		$this->assertSame( 3, $references[2]['original_id'] );
		$this->assertSame( 2, $references[2]['index'] );
	}

	public function test_ignores_blocks_without_references(): void {
		$content = '<!-- wp:paragraph --><p>Hello world</p><!-- /wp:paragraph -->';

		$this->assertSame( array(), $this->rewriter->extract( $content ) );
	}

	public function test_rewrite_swaps_id_at_top_level(): void {
		$content = '<!-- wp:image {"id":42} --><figure class="wp-block-image"><img class="wp-image-42"/></figure><!-- /wp:image -->';

		$references              = $this->rewriter->extract( $content );
		$references[0]['new_id'] = 500;

		$rewritten = $this->rewriter->rewrite( $content, $references );

		$reparsed = $this->rewriter->extract( $rewritten );
		$this->assertSame( 500, $reparsed[0]['original_id'] );
	}

	public function test_rewrite_swaps_nested_id(): void {
		$content = '<!-- wp:group --><div class="wp-block-group">'
			. '<!-- wp:image {"id":7} --><figure class="wp-block-image"><img class="wp-image-7"/></figure><!-- /wp:image -->'
			. '</div><!-- /wp:group -->';

		$references              = $this->rewriter->extract( $content );
		$references[0]['new_id'] = 800;

		$rewritten = $this->rewriter->rewrite( $content, $references );

		$reparsed = $this->rewriter->extract( $rewritten );
		$this->assertSame( 800, $reparsed[0]['original_id'] );
	}

	public function test_rewrite_leaves_unresolved_references_untouched(): void {
		$content = '<!-- wp:image {"id":42} --><figure class="wp-block-image"><img class="wp-image-42"/></figure><!-- /wp:image -->';

		// No new_id set on any reference.
		$rewritten = $this->rewriter->rewrite( $content, $this->rewriter->extract( $content ) );

		$this->assertSame( 42, $this->rewriter->extract( $rewritten )[0]['original_id'] );
	}
}

<?php

defined( 'ABSPATH' ) or die();

class Stealth_Update_Test extends WP_UnitTestCase {

	public function tearDown() {
		parent::tearDown();
	}


	//
	//
	// HELPER FUNCTIONS
	//
	//


	private function stealthify( $post_id ) {
		add_post_meta( $post_id, '_stealth-update', '1' );
	}

	private function create_post_with_update( $post_type = 'post', $stealth_update = true ) {
		$post = $this->factory->post->create_and_get( array( 'post_type' => $post_type ) );

		$post->post_title     = 'New title';

		if ( $stealth_update ) {
			$post->stealth_update = '1';
		}

		sleep( 2 );

		wp_update_post( $post );

		return get_post( $post->ID );
	}


	//
	//
	// FUNCTIONS FOR HOOKING ACTIONS/FILTERS
	//
	//


	//
	//
	// TESTS
	//
	//


	public function test_class_exists() {
		$this->assertTrue( class_exists( 'c2c_StealthUpdate' ) );
	}

	public function test_version() {
		$this->assertEquals( '2.5', c2c_StealthUpdate::version() );
	}

	public function test_init_action_triggers_do_init() {
		$this->assertNotFalse( has_action( 'init', array( 'c2c_StealthUpdate', 'do_init' ) ) );
	}

	public function test_quick_edit_custom_box_action_triggers_add_ui() {
		$this->assertNotFalse( has_action( 'quick_edit_custom_box', array( 'c2c_StealthUpdate', 'add_to_quick_edit' ) ) );
	}

	public function test_wp_insert_post_data_filter_triggers_wp_insert_post_data() {
		$this->assertNotFalse( has_filter( 'wp_insert_post_data', array( 'c2c_StealthUpdate', 'wp_insert_post_data' ), 2, 2 ) );
	}

	public function test_non_stealth_post_not_affected_on_update() {
		$post = $this->create_post_with_update( 'post', false );

		$this->assertEquals( 'New title', $post->post_title );
		$this->assertNotEquals( $post->post_date, $post->post_modified );
		$this->assertNotEquals( $post->post_date_gmt, $post->post_modified_gmt );
	}

	public function test_stealth_post_modified_date_unchanged_on_update( $post_type = 'post' ) {
		$post = $this->create_post_with_update();

		$this->assertEquals( 'New title', $post->post_title );
		$this->assertEquals( $post->post_date, $post->post_modified );
		$this->assertEquals( $post->post_date_gmt, $post->post_modified_gmt );

		return $post;
	}

	public function test_stealth_post_saves_meta_on_update() {
		$post = $this->create_post_with_update();

		$this->assertEquals( '1', get_post_meta( $post->ID, '_stealth-update', true ) );
	}

	public function test_revision_of_stealth_post_not_affected_on_update() {
		$post = $this->create_post_with_update( 'revision' );

		$this->assertNotEquals( $date, $post->post_modified );
	}

}

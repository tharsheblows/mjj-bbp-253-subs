<?php
/*
Plugin Name: bbPress 2.5.3 subscriptions
Version: 0.1
Description: Uses the subscription functions from bbPress 2.5.3 which sends emails individually rather than bbc'ing subscribers.
Author: Mary (JJ) Jay
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

// remove the bbPress subscription actions
remove_action( 'bbp_new_reply',    'bbp_notify_subscribers',                 11, 5 );
remove_action( 'bbp_new_topic',    'bbp_notify_forum_subscribers',           11, 4 );

// add in the new ones
add_action( 'bbp_new_reply',    'bbp_253_notify_subscribers',                 11, 5 );
add_action( 'bbp_new_topic',    'bbp_253_notify_forum_subscribers',           11, 4 );

/** Subscriptions *************************************************************/
  
  /**
   * Sends notification emails for new replies to subscribed topics
   *
   * Gets new post's ID and check if there are subscribed users to that topic, and
   * if there are, send notifications
   *
   * @since bbPress (r2668)
   *
   * @param int $reply_id ID of the newly made reply
   * @uses bbp_is_subscriptions_active() To check if the subscriptions are active
   * @uses bbp_get_reply_id() To validate the reply ID
   * @uses bbp_get_topic_id() To validate the topic ID
   * @uses bbp_get_forum_id() To validate the forum ID
   * @uses bbp_get_reply() To get the reply
   * @uses bbp_is_reply_published() To make sure the reply is published
   * @uses bbp_get_topic_id() To validate the topic ID
   * @uses bbp_get_topic() To get the reply's topic
   * @uses bbp_is_topic_published() To make sure the topic is published
   * @uses bbp_get_reply_author_display_name() To get the reply author's display name
   * @uses do_action() Calls 'bbp_pre_notify_subscribers' with the reply id,
   *                    topic id and user id
   * @uses bbp_get_topic_subscribers() To get the topic subscribers
   * @uses apply_filters() Calls 'bbp_subscription_mail_message' with the
   *                    message, reply id, topic id and user id
   * @uses apply_filters() Calls 'bbp_subscription_mail_title' with the
   *                    topic title, reply id, topic id and user id
   * @uses apply_filters() Calls 'bbp_subscription_mail_headers'
   * @uses get_userdata() To get the user data
   * @uses wp_mail() To send the mail
   * @uses do_action() Calls 'bbp_post_notify_subscribers' with the reply id,
   *                    topic id and user id
   * @return bool True on success, false on failure
   */
  function bbp_253_notify_subscribers( $reply_id = 0, $topic_id = 0, $forum_id = 0, $anonymous_data = false, $reply_author = 0 ) {
  
      // Bail if subscriptions are turned off
      if ( !bbp_is_subscriptions_active() )
          return false;
  
      /** Validation ************************************************************/
  
      $reply_id = bbp_get_reply_id( $reply_id );
      $topic_id = bbp_get_topic_id( $topic_id );
      $forum_id = bbp_get_forum_id( $forum_id );
  
      /** Reply *****************************************************************/
  
      // Bail if reply is not published
      if ( !bbp_is_reply_published( $reply_id ) )
          return false;
  
      /** Topic *****************************************************************/
  
      // Bail if topic is not published
      if ( !bbp_is_topic_published( $topic_id ) )
          return false;
  
      /** User ******************************************************************/
  
      // Get topic subscribers and bail if empty
      $user_ids = bbp_get_topic_subscribers( $topic_id, true );
      if ( empty( $user_ids ) )
          return false;
  
      // Poster name
      $reply_author_name = bbp_get_reply_author_display_name( $reply_id );
  
      /** Mail ******************************************************************/
  
      do_action( 'bbp_pre_notify_subscribers', $reply_id, $topic_id, $user_ids );
  
      // Remove filters from reply content and topic title to prevent content
      // from being encoded with HTML entities, wrapped in paragraph tags, etc...
      remove_all_filters( 'bbp_get_reply_content' );
      remove_all_filters( 'bbp_get_topic_title'   );
  
      // Strip tags from text
      $topic_title   = strip_tags( bbp_get_topic_title( $topic_id ) );
      $reply_content = strip_tags( bbp_get_reply_content( $reply_id ) );
      $reply_url     = bbp_get_reply_url( $reply_id );
      $blog_name     = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
  
      // Loop through users
      foreach ( (array) $user_ids as $user_id ) {
  
          // Don't send notifications to the person who made the post
          if ( !empty( $reply_author ) && (int) $user_id === (int) $reply_author )
              continue;
  
          // For plugins to filter messages per reply/topic/user
          $message = sprintf( __( '%1$s wrote:
  
  %2$s
  
  Post Link: %3$s
  
  -----------
  
  You are receiving this email because you subscribed to a forum topic.
  
  Login and visit the topic to unsubscribe from these emails.', 'bbpress' ),
  
              $reply_author_name,
              $reply_content,
              $reply_url
          );
  
          $message = apply_filters( 'bbp_subscription_mail_message', $message, $reply_id, $topic_id, $user_id );
          if ( empty( $message ) )
              continue;
  
          // For plugins to filter titles per reply/topic/user
          $subject = apply_filters( 'bbp_subscription_mail_title', '[' . $blog_name . '] ' . $topic_title, $reply_id, $topic_id, $user_id );
          if ( empty( $subject ) )
              continue;
  
          // Custom headers
          $headers = apply_filters( 'bbp_subscription_mail_headers', array() );
  
          // Get user data of this user
          $user = get_userdata( $user_id );
  
          // Send notification email
          wp_mail( $user->user_email, $subject, $message, $headers );
      }
  
      do_action( 'bbp_post_notify_subscribers', $reply_id, $topic_id, $user_ids );
  
      return true;
  }
  
  /**
   * Sends notification emails for new topics to subscribed forums
   *
   * Gets new post's ID and check if there are subscribed users to that topic, and
   * if there are, send notifications
   *
   * @since bbPress (r5156)
   *
   * @param int $topic_id ID of the newly made reply
   * @uses bbp_is_subscriptions_active() To check if the subscriptions are active
   * @uses bbp_get_topic_id() To validate the topic ID
   * @uses bbp_get_forum_id() To validate the forum ID
   * @uses bbp_is_topic_published() To make sure the topic is published
   * @uses bbp_get_forum_subscribers() To get the forum subscribers
   * @uses bbp_get_topic_author_display_name() To get the topic author's display name
   * @uses do_action() Calls 'bbp_pre_notify_forum_subscribers' with the topic id,
   *                    forum id and user id
   * @uses apply_filters() Calls 'bbp_forum_subscription_mail_message' with the
   *                    message, topic id, forum id and user id
   * @uses apply_filters() Calls 'bbp_forum_subscription_mail_title' with the
   *                    topic title, topic id, forum id and user id
   * @uses apply_filters() Calls 'bbp_forum_subscription_mail_headers'
   * @uses get_userdata() To get the user data
   * @uses wp_mail() To send the mail
   * @uses do_action() Calls 'bbp_post_notify_forum_subscribers' with the topic,
   *                    id, forum id and user id
   * @return bool True on success, false on failure
   */
  function bbp_253_notify_forum_subscribers( $topic_id = 0, $forum_id = 0, $anonymous_data = false, $topic_author = 0 ) {
  
      // Bail if subscriptions are turned off
      if ( !bbp_is_subscriptions_active() )
          return false;
  
      /** Validation ************************************************************/
  
      $topic_id = bbp_get_topic_id( $topic_id );
      $forum_id = bbp_get_forum_id( $forum_id );
  
      /** Topic *****************************************************************/
  
      // Bail if topic is not published
      if ( ! bbp_is_topic_published( $topic_id ) )
          return false;
  
      /** User ******************************************************************/
  
      // Get forum subscribers and bail if empty
      $user_ids = bbp_get_forum_subscribers( $forum_id, true );
      if ( empty( $user_ids ) )
          return false;
  
      // Poster name
      $topic_author_name = bbp_get_topic_author_display_name( $topic_id );
  
      /** Mail ******************************************************************/
  
      do_action( 'bbp_pre_notify_forum_subscribers', $topic_id, $forum_id, $user_ids );
  
      // Remove filters from reply content and topic title to prevent content
      // from being encoded with HTML entities, wrapped in paragraph tags, etc...
      remove_all_filters( 'bbp_get_topic_content' );
      remove_all_filters( 'bbp_get_topic_title'   );
  
      // Strip tags from text
      $topic_title   = strip_tags( bbp_get_topic_title( $topic_id ) );
      $topic_content = strip_tags( bbp_get_topic_content( $topic_id ) );
      $topic_url     = get_permalink( $topic_id );
      $blog_name     = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
  
      // Loop through users
      foreach ( (array) $user_ids as $user_id ) {
  
          // Don't send notifications to the person who made the post
          if ( !empty( $topic_author ) && (int) $user_id === (int) $topic_author )
              continue;
  
          // For plugins to filter messages per reply/topic/user
          $message = sprintf( __( '%1$s wrote:
  
  %2$s
  
  Topic Link: %3$s
  
  -----------
  
  You are receiving this email because you subscribed to a forum.
  
  Login and visit the topic to unsubscribe from these emails.', 'bbpress' ),
  
              $topic_author_name,
              $topic_content,
              $topic_url
          );
          $message = apply_filters( 'bbp_forum_subscription_mail_message', $message, $topic_id, $forum_id, $user_id );
          if ( empty( $message ) )
              continue;
  
          // For plugins to filter titles per reply/topic/user
          $subject = apply_filters( 'bbp_forum_subscription_mail_title', '[' . $blog_name . '] ' . $topic_title, $topic_id, $forum_id, $user_id );
          if ( empty( $subject ) )
              continue;
  
          // Custom headers
          $headers = apply_filters( 'bbp_forum_subscription_mail_headers', array() );
  
          // Get user data of this user
          $user = get_userdata( $user_id );
  
          // Send notification email
          wp_mail( $user->user_email, $subject, $message, $headers );
      }
  
      do_action( 'bbp_post_notify_forum_subscribers', $topic_id, $forum_id, $user_ids );
  
      return true;
  }
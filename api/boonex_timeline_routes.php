<?php

/**
 * Boonex Timeline API Routes
 * 
 * This file defines API endpoints for timeline management.
 */

$oTimeline = BxDolModule::getInstance('bx_timeline');

// Create a timeline post
function bx_api_timeline_create_post($aParams) {
    global $oTimeline;
    return $oTimeline->serviceApiCreatePost($aParams);
}

// Get timeline posts
function bx_api_timeline_get_posts($aParams) {
    global $oTimeline;
    return $oTimeline->serviceApiGetPosts($aParams);
}

// Comment on a post
function bx_api_timeline_comment_post($aParams) {
    global $oTimeline;
    return $oTimeline->serviceApiCommentPost($aParams);
}

// Like a post
function bx_api_timeline_like_post($aParams) {
    global $oTimeline;
    return $oTimeline->serviceApiLikePost($aParams);
}

// Share a post
function bx_api_timeline_share_post($aParams) {
    global $oTimeline;
    return $oTimeline->serviceApiSharePost($aParams);
}

<?php
#########################################################################################
#
# Copyright 2010-2011  Maya Studios (http://www.mayastudios.com)
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.
#
#########################################################################################


require_once(dirname(__FILE__) . '/../../api/commons.php');

MSCL_require_once('IInterlinkLinkResolver.php', __FILE__);


/**
 * Provides Interlink prefixes for most Wordpress blog links, like other posts, categories, tags, ...
 */
class WordpressLinkProvider implements IInterlinkLinkResolver
{
    const TYPE_CATEGORY = 'category';
    const TYPE_TAG = 'tag';
    const TYPE_ARCHIVE = 'archive';
    const TYPE_BLOGROLL = 'blogroll';

    /**
     * Implements @see IInterlinkLinkResolver::get_handled_prefixes().
     */
    public function get_handled_prefixes()
    {
        return array('', // link to other post; has no prefix
            'attachment', 'attach', 'att', 'file',
            'category', 'cat',
            'tag');
    }

    /**
     * Implements @see IInterlinkLinkResolver::resolve_link().
     */
    public function resolve_link($post_id, $prefix, $params)
    {
        // TODO: Add support for blogroll (links; see get_bookmarks()) and archive (see get_year_link())
        switch ($prefix)
        {
            case '':
                return $this->resolve_regular_link($params, $post_id);

            case 'att':
            case 'attach':
            case 'attachment':
            case 'file':
                return $this->resolve_attachment_link($params, $post_id);

            case 'category':
                return $this->resolve_category_link($params);

            case 'tag':
                return $this->resolve_tag_link($params);

            default:
                throw new Exception('Unexpected prefix: ' . $prefix);
        }
    }

    private function resolve_regular_link($params, $cur_post_id)
    {
        $link        = null;
        $title       = null; # null = auto title
        $is_external = false;
        $type        = null;

        // Absolute link
        if ($params[0][0] == '/')
        {
            $link = $params[0];
            if (count($params) == 1)
            {
                $title = $link;
            }

            return array($link, $title, false, null);
        }

        if (strpos($params[0], '@') !== false)
        {
            # Assume email address
            $link = $params[0];
            if (count($params) == 1)
            {
                $title = $link;
            }

            return array('mailto:' . $link, $title, false, self::TYPE_EMAIL_ADDRESS);
        }

        $ref_parts = explode('#', $params[0], 2);
        if (count($ref_parts) == 2)
        {
            $page_id = trim($ref_parts[0]);
            $anchor  = trim($ref_parts[1]);
            if (empty($page_id) || $page_id == $cur_post_id)
            {
                // link to section on this page
                return array('#' . $anchor, null, false, self::TYPE_SAME_PAGE_ANCHOR);
            }
        }
        else
        {
            $page_id = $params[0];
            $anchor  = '';
        }

        $post = MarkupUtil::get_post($page_id);
        if ($post === null)
        {
            // post not found
            throw new LinkTargetNotFoundException();
        }

        $is_attachment = MarkupUtil::is_attachment_type($post->post_type);

        // Determine title - but only if the title wasn't specified explicitely.
        if (count($params) == 1)
        {
            if ($is_attachment)
            {
                $title = MarkupUtil::get_attachment_title($post);
            }
            else
            {
                $title = apply_filters('the_title', $post->post_title);
            }

            if (empty($title))
            {
                $title = $page_id;
            }
        }

        // Posting must be published. Ignore the status for attachments.
        if ($is_attachment)
        {
            // attachment
            // NOTE: Unlike the "attachment:" prefix this doesn't link directly to the attached file but to a
            //   description page for this attachment.
            $link = get_attachment_link($post->ID);
            $type = IInterlinkLinkResolver::TYPE_ATTACHMENT;
        }
        else if ($post->post_status == 'publish')
        {
            // post or page
            $link = get_permalink($post->ID);
            // post_type: post|page
            $type = $post->post_type;
        }
        else
        {
            // Post/Page not published. Could be unpublished, trashed, ...
            throw new LinkTargetNotFoundException($post->post_status, $title);
        }

        if (!empty($anchor) && !$is_attachment)
        {
            // append anchor - but not for attachments
            $link .= '#' . $anchor;
        }

        return array($link, $title, $is_external, $type);
    }

    /**
     * Resolves a "attachment:" link. Note that the difference to "resolve_regular_link()" is that this method
     * also allows for the full filename to be used, checks whether the specified link is actually an
     * attachment, and allows a # in the name (what "resolve_regular_link()" interprets as HTML anchor).
     */
    private function resolve_attachment_link($params, $post_id)
    {
        $link        = null;
        $title       = null;
        $is_external = false;
        $type        = IInterlinkLinkResolver::TYPE_ATTACHMENT;

        $att_id = MarkupUtil::get_attachment_id($params[0], $post_id);
        if ($att_id === null)
        {
            // attachment not found
            throw new LinkTargetNotFoundException();
        }

        // Determine title - but only if the title wasn't specified explicitly.
        if (count($params) == 1)
        {
            $title = MarkupUtil::get_attachment_title($att_id);
        }

        $link = wp_get_attachment_url($att_id);

        return array($link, $title, $is_external, $type);
    }

    private function resolve_category_link($params)
    {
        $link        = null;
        $title       = null;
        $is_external = false;
        $type        = self::TYPE_CATEGORY;

        // Get the ID of a given category
        if (is_numeric($params[0]))
        {
            $category_id = (int) $params[0];
            if (!is_category($category_id))
            {
                throw new LinkTargetNotFoundException();
            }
        }
        else
        {
            $category_id = get_cat_ID($params[0]);
            if ($category_id == 0)
            {
                throw new LinkTargetNotFoundException();
            }
        }

        // Get the URL of this category
        $link = get_category_link($category_id);
        if (count($params) == 1)
        {
            $title = get_cat_name($category_id);
        }

        return array($link, $title, $is_external, $type);
    }

    private function resolve_tag_link($params)
    {
        $link        = null;
        $title       = null;
        $is_external = false;
        $type        = self::TYPE_TAG;

        // Get the ID of a given category
        if (is_numeric($params[0]))
        {
            $tag_id = (int) $params[0];
            $tag    = get_tag($tag_id);
            if ($tag === null)
            {
                throw new LinkTargetNotFoundException();
            }
        }
        else
        {
            $tag = get_term_by('name', $params[0], 'post_tag');
            if ($tag == false)
            {
                throw new LinkTargetNotFoundException();
            }
            $tag_id = $tag->term_id;
        }

        // Get the URL of this category
        $link = get_tag_link($tag_id);
        if (count($params) == 1)
        {
            $title = $tag->name;
        }

        return array($link, $title, $is_external, $type);
    }
}

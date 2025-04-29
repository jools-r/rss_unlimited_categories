<?php
if (txpinterface === 'admin') {
    if (!getRows("SHOW TABLES LIKE '".PFX."textpattern_category'")) {
        safe_create(
            'textpattern_category', "
            `article_id` int(11) NOT NULL default '0',
            `category_id` int(6) NOT NULL default '0',
            UNIQUE KEY (`article_id`,`category_id`)"
        );
    }

    register_callback('rss_uc_admin_tab_article_ui', 'article_ui', 'categories');
    register_callback('rss_uc_admin_tab_article_js', 'article');
    register_callback('rss_uc_admin_tab_category', 'category');

    add_privs('rss_uc_admin_tab_pref', '1,2');
    register_tab("extensions", "rss_uc_admin_tab_pref", "Unlim Cats");
    register_callback("rss_uc_admin_tab_pref", "rss_uc_admin_tab_pref");

    add_privs('plugin_prefs.rss_unlimited_categories', '1,2');
    register_callback('rss_uc_admin_tab_pref', 'plugin_prefs.rss_unlimited_categories');

    register_callback("rss_uc_admin_article_save", "article_posted");
    register_callback("rss_uc_admin_article_save", "article_saved");
    register_callback("rss_uc_admin_articles_deleted", "articles_deleted");
    register_callback("rss_uc_admin_categories_deleted", "categories_deleted");
} else {
    register_callback('rss_multi_url', 'pretext');

    if (class_exists('\Textpattern\Tag\Registry')) {
        Txp::get('\Textpattern\Tag\Registry')
            // ->register('function')
            // ->register('function', 'alias')
            ->register('rss_if_article_uc')
            ->register('rss_if_article_uc', 'rss_if_article_unlimited_category')
            ->register('rss_uc_article_list')
            ->register('rss_uc_article_list', 'rss_unlimited_categories_article_list')
            ->register('rss_uc_related')
            ->register('rss_uc_related', 'rss_unlimited_categories_related')
            ->register('rss_uc_cloud')
            ->register('rss_uc_cloud', 'rss_unlimited_categories_cloud')
            ->register('rss_uc_filedunder')
            ->register('rss_uc_filedunder', 'rss_unlimited_categories_filedunder')
            ->register('rss_uc_list')
            ->register('rss_uc_list', 'rss_unlimited_categories_list')
            ->register('rss_uc_count')
            ->register('rss_uc_count', 'rss_unlimited_category_count')
            ->register('rss_sct_permlink')
            ;
    }
}

// public

function rss_multi_url()
{
    global $attach_titles_to_permalinks, $permlink_mode, $pretext;

    // set messy variables
    $out = makeOut('id', 's', 'c', 'q', 'pg', 'p', 'month', 'author');
    if (!$out['id'] && !$out['s'] && !(txpinterface=='css')) {
        $subpath = preg_quote(preg_replace("/http:\/\/.*(\/.*)/Ui", "$1", hu), "/");
        $req = preg_replace("/^$subpath/i", "/", serverSet('REQUEST_URI'));
        extract(chopUrl($req));

        if (!empty($u1)) {
            switch($u1) {
            case 'atom':
                include txpath.'/publish/atom.php';
                exit(atom());
            case 'rss':
                include txpath.'/publish/rss.php';
                exit(rss());
            default:
                if ($permlink_mode == "section_id_title" || $permlink_mode == "section_title") {
                    if (empty($u2)) {
                        $out['s'] = (ckEx('section', $u1)) ? $u1 : '';
                        $is_404 = empty($out['s']);
                    } else {
                        if (isset($u2)) {
                            $rs = lookupByTitleSection($u2, $u1);
                            $out['id'] = $rs['ID'] ?? '';
                            $_GET['id'] = $rs['ID'] ?? '';
                            $out['s'] = $rs['Section'] ?? '';
                            $_GET['s'] = $rs['Section'] ?? '';
                            $is_404 = empty($out['s']);

                            if (!$out['s'] && empty($u3)) {
                                $out['c'] = (ckEx('category', $u2)) ? $u2 : null;
                                $_GET['c'] = (ckEx('category', $u2)) ? $u2 : null;
                                if (!empty($out['c'])) {
                                    $out['s'] = (ckEx('section', $u1)) ? $u1 : null;
                                    $_GET['s'] = (ckEx('section', $u1)) ? $u1 : null;
                                }
                                $is_404 = empty($out['c']);
                            }
                        }
                        if (!$out['id'] && isset($u3)) {
                            $rs = lookupByTitleSection($u3, $u1);
                            if (empty($rs['ID'])) {
                                $out['pg'] = $u3;
                                $is_404 = (empty($out['s']) or empty($out['id']));
                            } else {
                                $out['id'] = $rs['ID'] ?? '';
                                $out['s'] = $rs['Section'] ?? '';
                                $out['c'] = (ckEx('category', $u2)) ? $u2 : '';
                                $_GET['c'] = (ckEx('category', $u2)) ? $u2 : '';
                                $_GET['s'] = $rs['Section'] ?? '';
                                $_GET['id'] = $rs['ID'] ?? '';
                            }
                        }
                        if (!empty($u4)) {
                            $out['s'] = (ckEx('section', $u1)) ? $u1 : '';
                            $_GET['s'] = (ckEx('section', $u1)) ? $u1 : '';
                        }
                        if ($out['id'] or $out['c']) {
                            $out['s'] = (ckEx('section', $u1)) ? $u1 : '';
                            $out['c'] = (ckEx('category', $u2)) ? $u2 : '';
                            $_GET['c'] = (ckEx('category', $u2)) ? $u2 : '';
                            $_GET['s'] = (ckEx('section', $u1)) ? $u1 : '';
                        } else {
                            $is_404 = (empty($out['s']) or empty($out['id']));
                        }
                    }
                }
            }
        }
    }
}


//------------------------------------------------------------- TAGS
function rss_uc_filedunder($atts)
{
    global $thisarticle;
    extract(lAtts(array(
        'linktosection'  => $thisarticle['section'],
        'delim'          => '/',
        'suffix'          => '',
        'linked'         => 1,
        'parent'         => '',
        'usemessy'       => 0,
        'sort'           => 'title asc',

        'wraptag'        => '',
        'class'          => '',
        'break'          => ', ',
        'breakclass'     => '',
        'listwraptag'    => '',
        'label'          => '',
        'labeltag'       => '',
    ), $atts));

    $parents = ($parent) ? " AND c.parent IN('".join("','", doSlash(do_list_unique($parent)))."')" : "";

    $cats = array();
    $rsc = getRows("SELECT c.title, c.name FROM ".PFX."textpattern_category AS tc LEFT JOIN  ".PFX."txp_category AS c ON tc.category_id = c.id WHERE article_id = ".intval($thisarticle['thisid']).$parents." ORDER BY ".doSlash($sort));
    if ($rsc) {
        foreach ($rsc as $b) {
            if ($linked == 1) {
                $path = ($usemessy) ? hu."?s=".$linktosection."&amp;c=".strtolower($b['name']) : hu.$linktosection.$delim.strtolower($b['name']).$suffix;
                $cats[] = tag(htmlspecialchars($b['title']), 'a', ' href="'.$path.'" title="'.htmlspecialchars($b['title']).'"');
            } elseif ($linked == 2) {
                $cats[] = strtolower($b['name']);
            } elseif ($linked == 3) {
                $path = ($usemessy) ? hu."?s=".$linktosection."&amp;c=".strtolower($b['name']) : hu.strtolower($b['name']).$suffix;
                $cats[] = tag(htmlspecialchars($b['title']), 'a', ' href="'.$path.'" title="'.htmlspecialchars($b['title']).'"');
            } else {
                $cats[] = htmlspecialchars($b['title']);
            }
        }
        return doTag(
            doLabel($label, $labeltag).
            doWrap($cats, $wraptag, $break, $class, $breakclass),
            $listwraptag
        );
    }
    return '';
}


function rss_uc_list($atts)
{
    global $c;

    extract(lAtts(array(
        'limit'          => 999,
        'offset'         => 0,
        'section'        => '',
        'time'           => 'past',
        'parent'         => '',
        'showcount'      => 1,
        'showallcount'   => 0,
        'allcountlabel'  => 'All',
        'countlinked'    => 1,
        'linktosection'  => 'articles',
        'usemessy'       => 0,
        'listwraptag'    => '',
        'break'          => 'li',
        'wraptag'        => 'ul',
        'label'          => '',
        'labeltag'       => '',
        'class'          => '',
        'breakclass'     => '',
        'sort'           => 'title asc',
        'activeclass'    => '',
    ), $atts));

    $sections = rssBuildSctSql($section);
    $time = rssBuildTimeSql($time);

    $prnt = ($parent) ? " WHERE parent = '".doSlash($parent)."' " : "";
    $rsc = getRows("SELECT DISTINCT c.id, c.name, c.title FROM ".PFX."textpattern_category AS tc LEFT JOIN ".PFX."txp_category AS c ON tc.category_id = c.id ".$prnt."ORDER BY ".doSlash($sort)." LIMIT ".intval($offset).", ".intval($limit));

    if ($rsc) {
        $activeclass = ($activeclass) ? " class=\"".htmlspecialchars($activeclass)."\"" : "";

        $aq = "SELECT c.category_id, COUNT(*) AS cc FROM ".PFX."textpattern AS t LEFT JOIN ".PFX."textpattern_category AS c ON t.ID = c.article_id WHERE ".$sections." t.Status = 4 ".$time." GROUP BY c.category_id";
        $uc_count = array();
        if ($rsa = getRows($aq)) {
            foreach ($rsa as $r) {
                if (!empty($r['category_id'])) {
                    $uc_count[$r['category_id']] = $r['cc'];
                }
            }
        }

        $row = array();
        $total = 0;
        foreach ($rsc as $b) {
            if (!empty($uc_count[$b['id']])) {
                $path = ($usemessy) ? hu."?s=".$linktosection."&amp;c=".strtolower($b['name']) : hu.$linktosection."/".strtolower($b['name']);
                $count = ($showcount) ? " ({$uc_count[$b['id']]})" : "";
                $total += $uc_count[$b['id']];
                $row[] = ($countlinked) ?
                // Next two lines add activeclass to category link
                tag(htmlspecialchars($b['title'].$count), 'a', ' href="'.$path.'"'.($c == strtolower($b['name']) ? $activeclass : '').' title="'.htmlspecialchars($b['title']).'"') :
                tag(htmlspecialchars($b['title']), 'a', ' href="' . $path . '"'.($c == strtolower($b['name']) ? $activeclass : '').' title="' . htmlspecialchars($b['title']) . '"') . $count;
            }
        }

        if ($showallcount) {
            $path = hu.$linktosection."/".$parent;
            $count = ($showcount) ? " (".$total.") " : "";
            $row[] = ($countlinked) ?
                tag(htmlspecialchars($allcountlabel.$count), 'a', ' href="'.$path.'" title="'.htmlspecialchars($allcountlabel).'"') :
                tag(htmlspecialchars($allcountlabel), 'a', ' href="' . $path . '" title="' . htmlspecialchars($allcountlabel) . '"') . $count;
        }

        return doTag(
            doLabel($label, $labeltag).
            doWrap($row, $wraptag, $break, $class, $breakclass),
            $listwraptag
        ).n;
    }

    return '';
}


function rss_uc_count($atts)
{
    extract(lAtts(array(
        'name'      => '',
        'id'        => '',
        'section'   => '',
        'time'      => 'past',
    ), $atts));

    if ($name && !$id) {
        $id = safe_field('id', 'txp_category', "name='".doSlash($name)."'");
    }

    if ($id) {
        $sections = rssBuildSctSql($section);
        $time = rssBuildTimeSql($time);
        $cc = getThing(
            "SELECT COUNT(*) AS cc FROM ".PFX."textpattern AS t
            LEFT JOIN ".PFX."textpattern_category AS c ON t.ID = c.article_id
            WHERE ".$sections." c.category_id = '".intval($id)."'
            AND t.Status = 4 ".$time
        );
        return $cc;
    }
}


function rss_uc_cloud($atts)
{
    extract(lAtts(array(
        'section'        => '',
        'time'           => 'past',
        'limit'          => 999,
        'weightmin'      => 0,
        'parent'         => '',
        'linktosection'  => 'article',
        'usemessy'       => 0,
        'sort'           => 'title asc',
        'cloudwraptag'   => 'div',
        'wraptag'        => 'p',
        'break'          => ', ',
        'class'          => '',
        'breakclass'     => '',
        'label'          => '',
        'labeltag'       => '',
    ), $atts));

    $sections = rssBuildSctSql($section);
    $time = rssBuildTimeSql($time);
//    $parents = ($parent) ? " WHERE c.parent IN('".join("','", doSlash(do_list_unique($parent)))."')" : "";

    $q = "SELECT tc.category_id, c.name, c.title, c.parent, COUNT(*) AS cc FROM ".PFX."textpattern AS t
        LEFT JOIN ".PFX."textpattern_category AS tc ON t.ID = tc.article_id
        LEFT JOIN ".PFX."txp_category AS c ON tc.category_id = c.id
        WHERE ".$sections." t.Status = 4 ".$time." AND tc.category_id > 0
        GROUP BY tc.category_id HAVING cc > ".intval($weightmin)."
        ORDER BY cc DESC LIMIT ".intval($limit);

    if ($rsa = getRows($q)) {
        $max = $rsa[0]['cc'];
        $min = $rsa[count($rsa)-1]['cc'];
        $x = 200; $y = 100; // 200%, 100%
        $stepvalue = ($max - $min != 0) ? ($max - $min) / ($x - $y) : 1;
        $row = array();
        foreach ($rsa as $r) {
            $weight = $y + round(($r['cc']-$min) / $stepvalue);
            $style = ($weight > $y) ? ' style="font-size:'. $weight . '%;"' : '';
            $path = ($usemessy) ? hu."?s=".$linktosection."&amp;c=".strtolower($r['name']) : hu.$linktosection."/".strtolower($r['name']);
            $row[$r['title']] = tag(htmlspecialchars($r['title']), 'a', ' href="'.$path.'"'.$style.' title="'.htmlspecialchars($r['title']).'"');
        }
        ksort($row);
        return doTag(
            doLabel($label, $labeltag).
            doWrap($row, $wraptag, $break, $class, $breakclass),
            $cloudwraptag
        ).n;
    }
}


function rss_uc_article_list($atts)
{
    global $s, $c, $pg, $prefs, $thisarticle, $id, $has_article_tag;

    $thisarticle22 = $thisarticle;
    $s22 = $s;
    $c22 = $c;
    $pg22 = $pg;
    $prefs22 = $prefs;
    $id22 = $id;

    $actual_id = $id;
    extract(lAtts(array(
        'section'        => $s,
        'category'       => $c,
        'andcategory'    => '',
        'categorylogic'  => 'and',
        'usechildren'    => 0,
        'form'           => 'default',
        'limit'          => 999,
        'offset'         => 0,
        'time'           => 'past',
        'status'         => 'live',
        'sort'           => 'uPosted desc',
        'id'             => '',
        'hideself'       => '1',
        'filter'          => 0,
        'filterfield'      => '',
        'filtername'      => '',
    ), $atts));

    $parent = "";
    if ($usechildren) {

        $rs = safe_rows(
            "name",
            "txp_category",
            "parent='".doSlash($category)."' AND name != 'root'"
        );

        if ($rs) {
            $parent = $category;
            $category = "";
            foreach ($rs as $t) {
                $category.= $t['name'].",";
            }
        }
    }

    if ($id) {
        $rs = safe_row(
            "*,
            unix_timestamp(Posted) AS uPosted,
            unix_timestamp(Expires) AS uExpires,
            unix_timestamp(LastMod) AS uLastMod",
            "textpattern",
            "ID='".intval($id)."'"
        );

        if ($rs) {
            extract($rs);
            populateArticleData($rs);
            $thisarticle['parentcat'] = $parent;
            $thisarticle['thiscat'] = $category;
        }
        $article = fetch_form($form);
        $has_article_tag = true;
        $thisarticle = $thisarticle22;
        $s = $s22;
        $c = $c22;
        $pg = $pg22;
        $prefs = $prefs22;
        $id = $id22;

        return $article;
    }

    $filtersql = "";
    if ($filter && $filterfield && $filtername) {
        $subpath = preg_quote(preg_replace("/http:\/\/.*(\/.*)/Ui", "$1", hu), "/");
        $req = preg_replace("/^$subpath/i", "/", serverSet('REQUEST_URI'));
        extract(chopUrl($req));
        if ($u2 == $filtername && $u4) {
            $qt = (is_numeric($u4)) ? "" : "'";
            switch ($u3) {
            case 'lt':
                $filterop = "<";
                break;
            case 'gt':
                $filterop = " > ";
                break;
            default:
                $filterop = " = ";
            }
            $filtersql = " AND ".doSlash($filterfield)." $filterop ".$qt.doSlash($u4).$qt." ";
        }
    }

    $time = rssBuildTimeSql($time);
    $sections=rssBuildSctSql($section);
    $status=getStatusNum($status);

    $qa0 = array();
    $qa1 = array();
    $cc = 0;

    foreach (explode(',', $category) as $category) {
        if ($category) $catsql[] = " cat.name = '" . doSlash(urldecode($category)) . "' ";
    }
    $categories= isset($catsql) ? ' AND (' . join(' OR ', $catsql) . ') ' : "";

    foreach (explode(',', $andcategory) as $andcategory) {
        $cc++;
        if ($andcategory) {
            $andcatsql[] = " cat.name = '" . doSlash(urldecode($andcategory)) . "' ";
        }
    }
    $andcategories = isset($andcatsql) ? ' AND (' . join(' OR ', $andcatsql) . ') ' : "";

    if ($categories || (! $categories && ! $andcategories)) {
        $q0 = "SELECT DISTINCT t.ID FROM ".PFX."textpattern AS t
        LEFT JOIN ".PFX."textpattern_category AS tc ON t.ID = tc.article_id
        LEFT JOIN ".PFX."txp_category AS cat ON cat.id = tc.category_id
        WHERE ".$sections." Status = ".intval($status)." ".$categories.$filtersql.$time;
        $rsc = getRows($q0);

        if ($rsc) {
            foreach ($rsc as $a) {
                $qa0[] = $a['ID'];
            }
        }
    }
    if ($andcategories) {
        $q1 = "SELECT DISTINCT t.ID FROM ".PFX."textpattern AS t
        LEFT JOIN ".PFX."textpattern_category AS tc ON t.ID = tc.article_id
        LEFT JOIN ".PFX."txp_category AS cat ON cat.id = tc.category_id
        WHERE ".$sections." Status = ".intval($status)." ".$andcategories.$filtersql.$time;
        if ($cc > 1) {
            $q1 .= " GROUP BY t.ID HAVING COUNT(*) = ".intval($cc);
        }
        $rsc = getRows($q1);

        if ($rsc) {
            foreach ($rsc as $a) {
                $qa1[] = $a['ID'];
            }
        }
    }

    if ((strtolower($categorylogic) == 'and') && $qa0 && $qa1) {
        $qa = array_intersect($qa0, $qa1);
    } else {
        $qa = array_unique(array_merge($qa0, $qa1));
    }

    if ($qa) {
        $articles = " AND ID IN (".implode(',', array_map('intval', $qa)).")";
        if ($hideself) {
            $articles .= " AND ID <> '".intval($thisarticle['thisid'])."'";
        }

        $total = safe_count('textpattern', "1=1 " . $articles) - intval($offset);
        $numPages = ceil($total / intval($limit));
        $pg = (!$pg) ? 1 : $pg;
        $pgoffset = intval($offset) + (($pg - 1) * intval($limit)).', ';
        // send paging info to txp:newer and txp:older
        $pageout['pg']       = $pg;
        $pageout['numPages'] = $numPages;
        $pageout['s']        = $s;
        $pageout['c']        = $c;
        $pageout['total']    = $total;

        $GLOBALS['thispage'] = $pageout;
        $q2 = "1=1 $time $articles ORDER BY ".doSlash($sort)." LIMIT " . $pgoffset . intval($limit);

        $rs = safe_rows_start(
            "*,
            unix_timestamp(Posted) AS uPosted,
            unix_timestamp(Expires) AS uExpires,
            unix_timestamp(LastMod) AS uLastMod",
            "textpattern",
            $q2
        );

        if ($rs) {
            $count = 0;
            $articles = array();
            while ($a = nextRow($rs)) {
                ++$count;
                $comparing = $a['ID'];
                populateArticleData($a);
                global $thisarticle, $uPosted, $limit;
                $thisarticle['parentcat'] = $parent;
                $thisarticle['thiscat'] = $category;
                $thisarticle['is_first'] = ($count == 1);
                $thisarticle['is_last'] = ($count == numRows($rs));
                // define the article form
                $article = ($prefs['allow_form_override'] && $a['override_form']) ? fetch_form($a['override_form']) : fetch_form($form);

                if (!$hideself || $comparing != $actual_id) {
                    $articles[] = parse($article);
                }

                // sending these to paging_link(); Required?
                $uPosted = $a['uPosted'];
                $limit = $limit;
            }

            $has_article_tag = true;
            $thisarticle = $thisarticle22;
            $s = $s22;
            $c = $c22;
            $pg = $pg22;
            $prefs = $prefs22;
            $id = $id22;

            return join('', $articles);
        }
    }

    $thisarticle = $thisarticle22;
    $s = $s22;
    $c = $c22;
    $pg = $pg22;
    $prefs = $prefs22;
    $id = $id22;

    return '';
}


function rss_uc_related($atts)
{
    global $id, $thisarticle, $s;
    extract(lAtts(array(
        'section'   => $s,
        'form'      => 'default',
        'limit'     => 999,
        'offset'    => 0,
        'time'      => 'past',
        'sort'      => 'uPosted desc',
    ), $atts));

    $cats = array();
    $rsc = getRows(
        "SELECT c.title, c.name FROM ".PFX."textpattern_category AS tc
        LEFT JOIN  ".PFX."txp_category AS c ON tc.category_id = c.id
        WHERE article_id = ".intval($thisarticle['thisid'])
    );

    if ($rsc) {
        foreach ($rsc as $a) {
            extract($a); //FIXME
            $cats[$name] = $name;
        }
    }

    return rss_uc_article_list(
        array(
            'section'   => $section,
            'category'  => implode(',', $cats),
            'form'      => $form,
            'limit'     => $limit,
            'offset'    => $offset,
            'time'      => $time,
            'sort'      => $sort
        )
    );
}


function rss_if_article_uc($atts, $thing)
{
    global $thisarticle;
    assert_article();
    extract(lAtts(array(
        'name'        => '',
    ), $atts));

    if ($name) {
        $name = getRows(
            "SELECT name FROM ".PFX."textpattern_category AS tc
            LEFT JOIN  ".PFX."txp_category AS c ON tc.category_id = c.id
            WHERE article_id = ".intval($thisarticle['thisid'])."
            AND c.name IN('".join("','", doSlash(do_list_unique($name)))."')
            LIMIT 1"
        );
    }

    return parse($thing, $name);
}


function rss_sct_permlink($atts, $thing)
{
    global $thisarticle, $c;
    extract(lAtts(array(
        'isparent'    => 0,
        'useparent'   => 0,
        'findparent'   => 0,
        'inparents'   => '',
        'category'    => $c,
        'id'          => '',
    ), $atts));

    if ($useparent) {
        $rs = fetch("parent", "txp_category", "name", $category);
        if ($rs && $rs != 'root') {
            $category = $rs;
        }
    }

    $urltitle = $thisarticle['url_title'];
    $sct = $thisarticle['section'];

    if ($id) {
        $article = safe_row(
            "*,
            ID AS thisid,
            unix_timestamp(Posted) AS posted,
            unix_timestamp(Posted) AS uPosted,
            unix_timestamp(Expires) AS uExpires,
            unix_timestamp(LastMod) AS uLastMod",
            "textpattern",
            'ID = '.intval($id)
        );
        $urltitle = $article['url_title'];
        $sct = $article['Section'];
    }

    $catlink = ($category) ? "$category/" : "";
    $catlink = (isset($thisarticle['thiscat']) && $thisarticle['thiscat'] && $thisarticle['thiscat'] != $c) ? $thisarticle['thiscat']."/" : $catlink;
    $catlink = ($isparent) ? $thisarticle['parentcat']."/" : $catlink;

    if ($findparent) {
        $rsc = safe_rows(
            "category_id",
            "textpattern_category",
            "article_id = ".intval($thisarticle['thisid'])
        );

        if ($rsc) {
            $thecats = array();
            foreach ($rsc as $cat) {
                $thecats[] = $cat['category_id'];
            }
            $rs = safe_rows("parent", "txp_category", "id IN (".implode(",", $thecats).")");
            if ($rs) {
                foreach ($rs as $t) {
                    $linkcat = $t['parent'];
                    if (in_array($linkcat, explode(",", $inparents))) {
                        $catlink = $linkcat."/";
                    }
                }
            }
        }
    }
    $url = hu.$sct."/$catlink".$urltitle;
    return tag(parse($thing), 'a', ' href="'.$url.'" title="'.gTxt('permanent_link').'"');
}

//=============================================================
function rssBuildSctSql($section)
{
    if ($section) {
        $sctsql = array();
        $notsctsql = array();
        foreach (do_list_unique($section) as $section) {
            switch (substr(trim($section), 0, 1)) {
            case '*':
                $sctsql[] = " (section LIKE '%') ";
                break;
            case '!':
                $notsctsql[] = " AND (section != '" . doSlash(str_replace('!', '', $section)) . "') ";
                break;
            default:
                $sctsql[] = " (section = '" . doSlash($section) . "') ";
            }
        }
        $sections = ' 1=1 AND (' . join(' OR ', $sctsql) . @join(' AND ', $notsctsql) . ') AND ';
    } else {
        $sections = '';
    }
    return $sections;
}


function rssBuildTimeSql($time)
{
    global $prefs;

    switch ($time) {
    case 'any':
        $time = "";
        break;
    case 'future':
        $time = " AND Posted > now()";
        break;
    default:
        $time = " AND Posted <= now()";
    }

    if (!$prefs['publish_expired_articles']) {
        $time .= " AND (Expires IS NULL OR Expires > now() OR Expires = 0) ";
    }

    return $time;
}

function rss_multiTreeSelectInput($selectname = "", $array = "", $value = "")
{
    global $rss_unlim_sel_size;
    $out = '<select id="rss_uc_multiselect_id" name="'.$selectname.'[]" class="list" size="'.intval($rss_unlim_sel_size).'" multiple>';

    foreach ($array as $a) {
        extract($a);
        if ($name=='root') {
            continue;
        }
        $selected = in_array($id, $value, true) ? ' selected="selected"' : '';
        $name = htmlspecialchars($name);
        $sp = ($level > 0) ? str_repeat(sp.sp.sp, $level) : '';
        $out .= '<option value="'.intval($id).'"'.$selected.'>'.$sp.addslashes($title).'</option>';
    }
    $out .= '</select>';

    return $out;
}


// admin side _________________________________
function rss_uc_admin_article_save($event, $step, $rs)
{
    if ($ID = intval($rs['ID'])) {
        safe_delete("textpattern_category", "article_id=" . $ID);

        $vals = ps('Cats');
        if (is_array($vals)) {
            $vals = array_map('intval', array_unique($vals));
            foreach ($vals as $val) {
                if ($val > 0) {
                    safe_insert(
                        "textpattern_category",
                        "category_id = ".$val.",
                        article_id=".$ID
                    );
                }
            }
        }
    }
}


function rss_uc_admin_articles_deleted($event, $ids, $step='')
{
    if (is_array($ids)) {
        $ids = array_map('intval', $ids);
        $ids = array_filter($ids); // Remove any zero or negative values
        if (!empty($ids)) {
            $id = implode(',', $ids);
            safe_delete(
                "textpattern_category",
                "article_id IN (".$id.")"
            );
        }
    }
}


function rss_uc_admin_categories_deleted($event, $ids, $step='')
{
    if (is_array($ids)) {
        $ids = array_map('intval', $ids);
        $ids = array_filter($ids); // Remove any zero or negative values
        if (!empty($ids)) {
            $id = implode(',', $ids);
            safe_delete(
                "textpattern_category",
                "category_id IN (".$id.")"
            );
        }
    }
}


function rss_uc_admin_tab_pref($event, $step)
{
    global $prefs, $rss_unlim_sel_size, $rss_unlim_sel_parent, $rss_unlim_sel_sections;

    $message='';

    // Initialize preferences if not set
    if (!isset($rss_unlim_sel_size)) {
        set_pref('rss_unlim_sel_size', 5, 'rss_uc', 1);
    }

    if (!isset($rss_unlim_sel_parent)) {
        set_pref('rss_unlim_sel_parent', '', 'rss_uc', 1);
    }

    if (!isset($rss_unlim_sel_sections)) {
        set_pref('rss_unlim_sel_sections', '', 'rss_uc', 1);
    }

    if (!isset($prefs['rss_unlim'])) {
        set_pref('rss_unlim', '', 'rss_uc', 1);
        $qq = '';
    } else {
        $qq = $prefs['rss_unlim'];
    }

    if (!$rss_unlim_sel_size) {
        $rss_unlim_sel_size = '5';
    }

    if (ps("save")) {
        // pagetop("Unlimited Categories Prefs", "Preferences Saved");
        // Validate and sanitize input
        $sel_size = max(1, min(20, intval(ps('rss_unlim_sel_size'))));
        $sel_parent = doSlash(ps('rss_unlim_sel_parent'));
        $sel_sections = doSlash(ps('rss_unlim_sel_sections'));

        set_pref('rss_unlim_sel_size', $sel_size, 'rss_uc', 1);
        set_pref('rss_unlim_sel_parent', $sel_parent, 'rss_uc', 1);
        set_pref('rss_unlim_sel_sections', $sel_sections, 'rss_uc', 1);

        // Handle rss_unlim checkboxes
        $rss_unlim = (isset($_POST['rss_unlim'])) ? implode(',', array_map('doSlash', $_POST['rss_unlim'])) : '';
        set_pref('rss_unlim', $rss_unlim, 'rss_uc', 1);
        header("Location: index.php?event=rss_uc_admin_tab_pref");
        exit;
    }

    pagetop("Unlim Cats Prefs", $message);

    if (ps("fix")) {
        // Fix broken category links
        safe_query(
            "DELETE tc FROM ".PFX."textpattern_category AS tc
            LEFT JOIN ".PFX."txp_category AS c ON tc.category_id = c.id
            WHERE c.id IS NULL"
        );
    }

    // Check for broken links
    $rs = getRows(
        "SELECT COUNT(*) AS cc FROM ".PFX."textpattern_category AS tc
        LEFT JOIN ".PFX."txp_category AS c ON tc.category_id = c.id
        WHERE c.id IS NULL"
    );

    $rsq = $rs[0]['cc'] ?? 0;

    if ($rsq) {
        $chk = "<b>Detect ".intval($rsq)." non-existent links</b>&nbsp;&nbsp;".
        fInput("submit", "fix", "FIX IT", "publish");
    } else {
        $chk = "<b>Status OK</b>";
    }

    $rs = getTree('root', 'article');

    // Sanitize output
    $sel_size = htmlspecialchars($rss_unlim_sel_size);
    $sel_parent = htmlspecialchars($rss_unlim_sel_parent);
    $sel_sections = htmlspecialchars($rss_unlim_sel_sections);

    $out = array();

    $out[] = hed('Config write article tab', 2);

    $out[] =
    inputLabel(
        'rss_unlim_sel_size',
        Txp::get('\Textpattern\UI\Input', 'rss_unlim_sel_size', 'text', $sel_size)->setAtts(array(
            'id'        => 'rss_unlim_sel_size',
            'size'      => INPUT_SMALL,
        )),
        'Select List Size', '', array('class' => 'txp-form-field')
    ) .
    inputLabel(
        'rss_unlim_sel_parent',
        Txp::get('\Textpattern\UI\SelectTree', 'rss_unlim_sel_parent', $rs, $sel_parent)->setAtts(array(
            'id'        => 'rss_unlim_sel_parent',
        )),
        //treeSelectInput('rss_unlim_sel_parent', $rs, $sel_parent),
        'Parent for display', '', array('class' => 'txp-form-field')
    ) .
    inputLabel(
        'rss_unlim_sel_sections',
        Txp::get('\Textpattern\UI\Input', 'rss_unlim_sel_sections', 'text', $sel_sections)->setAtts(array(
            'id'        => 'rss_unlim_sel_sections',
            'size'      => INPUT_REGULAR,
        )).
        "<br>Sample: section1,section2",
        'Display only in sections', '', array('class' => 'txp-form-field')
    ) .
    inputLabel(
        'rss_unlim',
        Txp::get('\Textpattern\UI\Checkbox', 'rss_unlim[]', 'hide12', ((strpos($qq, 'hide12') === false) ? 0 : 1))->setAtts(array(
            'id'        => 'rss_unlim',
        )),
        //checkbox('rss_unlim[]', 'hide12', ((strpos($qq, 'hide12') === false) ? 0 : 1)),
        'Hide Category1 and Category2', '', array('class' => 'txp-form-field')
    ) .
    inputLabel(
        'check_rss_uc',
        $chk,
        'Check rss_uc', '',  array('class' => 'txp-form-field')
    );

    $out[] = graf(
        fInput("submit", "save", gTxt("save"), "publish").
        eInput("rss_uc_admin_tab_pref").
        sInput('saveprefs'),
        array('class' => 'txp-edit-actions')
    );

    echo hed("Unlimited Categories Preferences", 1, array('class' => 'txp-heading')).
         form(join('', $out), '', '', 'post', 'txp-edit');
}


function rss_uc_admin_tab_category($event = '', $step = '')
{
    global $prefs;

    $rs = safe_rows(
        "category_id, COUNT(*) AS cc",
        "textpattern_category",
        "1=1 GROUP BY category_id"
    );

    if (empty($rs)) {
        return;
    }

    $ss = ":";

    foreach ($rs as $t) {
        $ss .= "{$t['category_id']}={$t['cc']}:";
    }

    // Hide category1/category2 counts if category1 & 2 are hidden in settings
    $js0 = "";
    if (isset($prefs['rss_unlim']) && strpos($prefs['rss_unlim'], 'hide12') !== false) {
        $js0 .= '    rss_pp.innerHTML = rss_pp.innerHTML.replace(/&nbsp;\(\d+\)/g, "");';
    }

    $js = <<<EOF
var rss_ss = "$ss";
var rss_all, rss_pp, rss_tt;
rss_all = document.evaluate("//*[@id=\"category_article_form\"]/p/input[@name=\"selected[]\"]", document, null, XPathResult.UNORDERED_NODE_SNAPSHOT_TYPE, null);
for (var i = 0; i < rss_all.snapshotLength; i++) {
    rss_tt = rss_all.snapshotItem(i);
    rss_pp = rss_tt.parentNode;

    rr = new RegExp("^.*:"+rss_tt.value+"=", "g");
    se = rss_ss.replace(rr, "");
    se = se.replace(/:.*$/g, "");
    {$js0}
    var spanTag = document.createElement("span");
    spanTag.className = "rss_uc_num";
    spanTag.innerHTML = '&nbsp;['+se+']';
    rss_pp.appendChild(spanTag, rss_tt);
}
EOF;

    if (class_exists('\Textpattern\UI\Script')) {
        echo n. Txp::get('\Textpattern\UI\Script')->setContent($js);
    } else {
        echo n . script_js($js);
    }
}


/**
 * Insert / replace categories group.
 */
function rss_uc_admin_tab_article_ui($evt, $stp, $data, $rs)
{
    global $prefs, $rss_unlim_sel_parent, $rss_unlim_sel_sections, $rss_uc_fired;

    // Workaround to prevent double-firing
    if ($rss_uc_fired) {
        return;
    }

    $rsc = array();
    $ID = empty($GLOBALS['ID']) ? intval(gps('ID')) : intval($GLOBALS['ID']);

    if ($ID) {
        $rsc = safe_column_num(
            "category_id",
            "textpattern_category",
            "article_id = " . $ID
        );
    }

    if (empty($rss_unlim_sel_parent)) {
        $rss_unlim_sel_parent = 'root';
    }

    $rs = getTree($rss_unlim_sel_parent, 'article');

    $mtsi = "";

    if ($rs) {
        $mtsi = rss_multiTreeSelectInput('Cats', $rs, $rsc);
    }

    $out = br.$mtsi.graf(
        eLink('category', 'list', '', '', gTxt('edit'), '', '', '', 'txp-option-link').
        '&nbsp;&nbsp;&nbsp;&nbsp;'.
        '<a href="#" id="rss_uc_deselect">Deselect all</a>'
    );

    if (isset($prefs['rss_unlim']) && strpos($prefs['rss_unlim'], 'hide12') !== false) {
        $data = $out;
    } else {
        $data = $out.$data;
    }

    $rss_uc_fired = true;

    return $data;
}


function rss_uc_admin_tab_article_js($event, $step)
{
    global $prefs, $rss_unlim_sel_parent, $rss_unlim_sel_sections;

    // write tab js: show/hide by section
    $js0 = '';
    if ($rss_unlim_sel_sections) {
        $js0 .= <<<EOF0
    $("#section").change(function () {
        rss_uc_sections=",$rss_unlim_sel_sections,";
        if (rss_uc_sections.search(","+document.getElementById('section').value+",") == -1) {
            $("#txp-categories-group").hide();
        } else {
            $("#txp-categories-group").show();
        }
    }).change();
EOF0;
    }

    // write tab js: wrapper + deselect button
    $js = <<<EOF
$(document).ready(function() {
$js0
    document.getElementById('rss_uc_deselect').addEventListener("click", (e) => {
        e.preventDefault();
        document.getElementById('rss_uc_multiselect_id').selectedIndex=-1;
    });
});
EOF;

    if (class_exists('\Textpattern\UI\Script')) {
        echo n. Txp::get('\Textpattern\UI\Script')->setContent($js);
    } else {
        echo n . script_js($js);
    }
}

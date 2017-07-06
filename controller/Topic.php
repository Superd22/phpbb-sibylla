<?php namespace scfr\sibylla\controller;
class Topic {
  /** @param \phpbb\db\driver\driver_interface */
  protected $db;
  /** @param \phpbb\template\template */
  protected $template;
  protected $user_cache;
  protected $topic_set_up = false;
  protected $topicIsDpt = false;

  function __construct(\phpbb\db\driver\driver_interface $db, \phpbb\template\template $template) {
    $this->db = $db;
    $this->template = $template;
  }

  public function set_ju_rank_and_dept(&$event) {
    error_reporting(0);
    $poster_id = $event["row"]["user_id"];
    if(!isset($this->user_cache[$poster_id]) || $this->user_cache[$poster_id]['JULIET']['done'] != true) {
      $ju = $this->db->sql_query('SELECT * FROM star_fleet WHERE id_forum = "'.$poster_id.'" ');
      $raw = $this->db->sql_fetchrow($ju);
      if(isset($raw['id'])) {
        // Déclaration des vars
        $this->user_cache[$poster_id]['JULIET']['has_guild'] = true;
        $this->user_cache[$poster_id]['JULIET']['is_juliet'] = true;
        $squad = $raw['squad'];
        // Récupération du grade du joueur
        $sq = $this->db->sql_query('SELECT * FROM star_rank WHERE id="'.$raw['grade'].'"');
        $grade = $this->db->sql_fetchrow($sq);
        $grade['url'] = str_replace('http://www.starcitizen.fr/', '/',$grade['url']);
        $this->user_cache[$poster_id]['JULIET']['grade'] = $grade;
        $this->user_cache[$poster_id]['JULIET']['grade']['url_s'] = str_replace('.png','_s.png',$grade['url']);
        // Récupération du squad
        if($squad > 0) {
          $sq = $this->db->sql_query('SELECT nom,logo,members FROM star_squad WHERE id = "'.$squad.'" ');
          $sq_row = $this->db->sql_fetchrow($sq);
          // Easy récup
          $sq_row['logo'] = str_replace('http://', '//', $sq_row['logo']);
          $this->user_cache[$poster_id]['JULIET']['sq_id'] = $squad;
          $this->user_cache[$poster_id]['JULIET']['sq_name'] = $sq_row['nom'];
          $this->user_cache[$poster_id]['JULIET']['sq_logo'] = $sq_row['logo'];
          $this->user_cache[$poster_id]['JULIET']['sq_link'] = 'https://starcitizen.fr/Flotte/?page=squad&m=view&id='.$squad;
          // Récupération pos dans squad.
        }
      }
      $this->user_cache[$poster_id]['JULIET']['done'] = true;
    }

    if(isset($this->user_cache[$poster_id]['JULIET']['is_juliet'])) {
      error_reporting(0);
      $cache = $event["post_row"];
      $tpl = array(
        'J_IS_JULIET' =>  $this->user_cache[$poster_id]['JULIET']['is_juliet'],
        'J_HAS_GUILD' =>  $this->user_cache[$poster_id]['JULIET']['has_guild'],
        'J_SQ_NAME'		=>  $this->user_cache[$poster_id]['JULIET']['sq_name'],
        'J_SQ_LOGO'		=>  $this->user_cache[$poster_id]['JULIET']['sq_logo'],
        'J_SQ_ID'			=>  $this->user_cache[$poster_id]['JULIET']['sq_id'],
        'J_U_SQ' 			=>  $this->user_cache[$poster_id]['JULIET']['sq_link'],
        'JU_RANK'			=>  $this->user_cache[$poster_id]['JULIET']['grade']['name'],
        'JU_RANK_IMG'	=>  $this->user_cache[$poster_id]['JULIET']['grade']['url_s'],
      );
      $cache = array_merge($cache, $tpl);
      $event["post_row"] = $cache;
    }

  }

  public function custom_parsing(&$event) {
    $message = $event["post_row"]["MESSAGE"];
    $cache = $event["post_row"];
    // ESCADRON JULIET {{ }}
    $message = preg_replace('/\{\{([^\]]+)\}\}/',"<a class='jquparseme scfr_sq_link'>$1</a>",$message);
    $this->ju_inc_v2_parse($message);
    $cache["MESSAGE"] = $message;
    $event["post_row"] = $cache;
  }

  private function ju_inc_v2_parse(&$message) {
    $juIncV2 = array();
    $need_angular = false;
    preg_match_all("/\[JU_([a-zA-Z_]+) ([a-zA-Z0-9 #|?!$%]*)\]/",$message,$juIncV2);
    if(isset($juIncV2[0][0])) {
      $need_angular = true;
      echo '<link rel="stylesheet" href="https://starcitizen.fr/Forum/styles/scfrV3/theme/juliet.css" type="text/css">';
      foreach($juIncV2[1] as $key=>$mode) {
        switch($mode) {
          case "TAGS":
          $message = str_replace($juIncV2[0][$key],'<div class="ju_v15_pannel">
          <div class="user_info_tags">
            <ju-tag-owner-included ng-init="ladid = '.$juIncV2[2][$key].';forum=true; "></ju-tag-owner-included>
            </div>
          </div>',
          $message);
          //	initTag
          break;
          case "TAG":
          $tags = explode('|',$juIncV2[2][$key]);
          $message = str_replace($juIncV2[0][$key],'<div class="ju_v15_pannel">
          <div class="user_info_tags">
          <ju-tag-single-included ng-init="initTag = \''.$tags[1].'\';initCat = \''.$tags[0].'\';"></ju-tag-single-included>
          </div></div>',
          $message);
          break;
          case "TAG_RECHERCHE":
          $message = str_replace($juIncV2[0][$key],'<div class="ju_v15_pannel">
          <div class="user_info_tags">
          <ju-tag-research-included ng-init=\'initQuery = "'.$juIncV2[2][$key].'";forum=true; \'></ju-tag-research-included>
          </div></div>',
          $message);
          break;
          case "EVENT":
          $message = str_replace($juIncV2[0][$key],'<div class="ju_v15_pannel"><div ng-module="Calendar">
          <div ui-view="EventIncluded" ng-init="initeId = '.$juIncV2[2][$key].'"></div>
          </div></div>',
          $message);
          break;
          case "EVENT_INVIT":
          $message = str_replace($juIncV2[0][$key],'<div class="ju_v15_pannel"><div ng-module="Calendar">
          <div ui-view="InvitIncluded" ng-init="initeId = '.$juIncV2[2][$key].'"></div>
          </div></div>',
          $message);
          break;
        }
      }
    }

    if($need_angular)
    $this->template->assign_vars(array(
      "JU_NEED_ANGULAR" => $need_angular,
    ));
  }

  public function handleOneTimeFire(&$event) {
    if(!$this->topic_set_up) {
      $this->check_dpt_memberlist($event);
      $this->topic_set_up = true;
    }
  }


  private function check_dpt_memberlist(&$event) {
    $topic_data = $event["topic_data"];
    if(strpos($event["topic_data"]['topic_title'], "[DPT]") !== false) {
      $topic_data['topic_title'] = preg_replace("~\[DPT\][ ]*~","", $topic_data['topic_title']);
      $event["topic_data"] = $topic_data;
    }
    $this->add_dpt_memberlist($event);
  }

  private function add_dpt_memberlist(&$event) {
    GLOBAL $forum_ju_dpt_id;
    $sq_name = $event["topic_data"]['topic_title'];
    $ju = $this->db->sql_query('SELECT id FROM star_squad WHERE nom LIKE "'.addslashes($sq_name).'" LIMIT 1 ');
    $raw = $this->db->sql_fetchrow($ju);
    if($raw['id'] > 0) {
      $forum_ju_dpt_id = $raw['id'];
      $this->topicIsDpt = true;
      $this->template->assign_vars(array("S_JU_DISPLAY_DPT" => true));
    }
  }

  // Function set_ju_dept
  // fetches and sets departments logo and bans for sibylla forums
  // @param $event phpbb_event containing row and topic_row
  // @return (bool) $hasDpt whever or not that topic was a dpt.
  public function set_ju_dept(&$event) {
    $row = $event["row"];
    // Verification departement
    $topicIsDept = $ju_ban = $hasDpt = $deptBan = false;
    $topicShow = true;
    $name = '';
    // Si c'est soit-disant un DPT.
    if(strpos($row['topic_title'],'[DPT] ') !== FALSE) {
      $name = str_replace('[DPT] ', '', $row['topic_title']);
      $ju = $this->db->sql_query('SELECT id,logo FROM star_squad WHERE nom LIKE "'.$name.'" ');
      $raw = $this->db->sql_fetchrow($ju);
      $topicIsDept = true;
      $hasDpt = true;
      $topicShow = false;
      $row['topic_title'] = $name;
      $deptBan = $raw['logo'];
    }
    if(isset($raw) && $raw['id'] != 0) {
      $is_ju_forum = true;
      if(isset($raw['ban'])) $ju_ban = $raw['ban'];
    }
    if($hasDpt)	$this->template->assign_vars(array("hasDpt" => true));

    $cache = $event["topic_row"];
    $tpl = array(
      'S_TOPIC_DEPT'					=> $topicIsDept,
      'S_TOPIC_JU_BAN'				=> $deptBan,
      'S_TOPIC_SHOW'					=> $topicShow,
    );
    $cache = array_merge($cache, $tpl);
    $event["topic_row"] = $cache;

    return $hasDpt;
  }
}
?>

<?php namespace scfr\sibylla\controller;
class Forum {
  /** @param \phpbb\db\driver\driver_interface */
  protected $db;
  /** @param \phpbb\template\template */
  protected $template;
  /** @param boolean */
  private $is_in_sibylla;
  /** @param boolean */
  private $has_dpt;
  /** @param boolean */
  private $order_handled;
  private $last_topic;

  function __construct(\phpbb\db\driver\driver_interface $db, \phpbb\template\template $template) {
    $this->db = $db;
    $this->template = $template;

  }

  // Function set_globals
  // Fetches and sets if forum is a squad forum, and if is inside sibylla QG's
  // (uses $template to sets var)
  // @param $event a PHPBB event containing forum_data.
  public function set_globals(&$event) {
    $forum_data = $event["forum_data"];
    // AJOUT SAVOIR SI FORUM EST SQUAD
    $is_ju_forum = $ju_ban = false;
    $ju = $this->db->sql_query('SELECT id,ban FROM star_squad WHERE nom= "'.$forum_data['forum_name'].'" ');
    $raw = $this->db->sql_fetchrow($ju);
    if($raw['id'] != 0) {
      $is_ju_forum = true;
      $ju_ban = $raw['ban'];
    }
    // AJOUT SAVOIR SI FORUM EST MILITAIRE, COPRO, PIRATE
    //ET ALORS VARIABLE S_JU_ICONE
    $forum_is_sibylla = false;
    if(!$this->is_in_sibylla) {
      $tab = unserialize($forum_data['forum_parents']);
      if ((isset($tab[28]) && is_array($tab[28])) || $forum_data["forum_id"] == 28) $this->is_in_sibylla = TRUE;
    }

    $tpl = array(
      'S_FORUM_IS_SQUAD'		=> $is_ju_forum,
      'S_JU_BAN'				=> $ju_ban,
      'S_FORUM_IN_SIB'  => $this->is_in_sibylla,
    );

    $this->template->assign_vars($tpl);
  }

  public function is_sibylla($check = false) {
    if($check) {
      if(!$this->is_in_sibylla) {
        $tpl = $this->accessProtected($this->accessProtected($this->template, "context"), "tpldata")["navlinks"][0]["FORUM_ID"];
        if($tpl == 28) $this->is_in_sibylla = true;
      }
    }

    return $this->is_in_sibylla;
  }

  public function set_has_dpt() {
    $this->has_dpt = true;
  }


  // Function squad_forum
  // Fetches and displays logo for sub-forums in sibylla
  // (uses $event to display data)
  // @param $event a phpbb event having row and forum_row
  public function squad_forum(&$event) {
    $row = $event["row"];

    if(!$this->is_in_sibylla) {
      $tab = unserialize($row['forum_parents']);
      if ((isset($tab[28]) && is_array($tab[28])) || $row["forum_id"] == 28) $this->is_in_sibylla = true;
    }

    if($this->is_in_sibylla) {
      $is_squad_fo = $sq_logo = false;
      $sq = $this->db->sql_query('SELECT * FROM star_squad WHERE nom = "'.$row['forum_name'].'" ');
      $sq_row = $this->db->sql_fetchrow($sq);
      if(is_array($sq_row)) {
        $is_squad_fo = true;
        $sq_logo = $sq_row['logo'];
      }
      $this->db->sql_freeresult($sq);

      $cache = $event["forum_row"];
      $cache["S_SQUAD_LOGO"] = $sq_logo;
      $event["forum_row"] = $cache;
    }
  }

  private function accessProtected($obj, $prop) {
    $reflection = new \ReflectionClass($obj);
    $property = $reflection->getProperty($prop);
    $property->setAccessible(true);
    return $property->getValue($obj);
  }

  // Function sort_departments
  // Sorts the departments in a forum to alphabetical order and leaves the other topic untouched
  // (uses $event to set topic_row data)
  // @param $event an event containing the topic_row of the forum.
  public function sort_departments(&$event) {
    if($this->last_topic == false)
    $this->last_topic = $event["topic_list"][sizeof($event["topic_list"]) - 1];
    // If that's the last topic, means we have the complete template.
    if($event["topic_id"] === $this->last_topic) {
      $topic_row = $this->accessProtected($this->accessProtected($this->template, "context"), "tpldata")["topicrow"];
      if($this->has_dpt) {
        foreach($topic_row as $i => $topic) {

          if(strpos($topic['TOPIC_TITLE'], "[DPT]") === 0) {
            $dpts[] = $topic;
            unset($topic_row[$i]);
          }
        }

        usort($dpts, array(&$this, "JU_DPT_SORT"));
        $topic_row = array_merge($dpts, array_values($topic_row));
        $this->template->destroy_block_vars("topicrow");
        $this->template->assign_block_vars_array("topicrow", $topic_row);
      }
      $this->order_handled = true;
    }
  }

  private function JU_DPT_SORT($a, $b) {
    return strnatcmp(strtolower($a['TOPIC_TITLE']), strtolower($b['TOPIC_TITLE']) );
  }

  public function has_departments() {
    return $this->has_dpt;
  }

}
?>

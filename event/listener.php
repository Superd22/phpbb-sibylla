<?php namespace scfr\sibylla\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
  /** @var \phpbb\template\template */
  protected $template;
  /** @var \phpbb\user */
  protected $user;
  /** @param \phpbb\db\driver\driver_interface */
  protected $db;
  private $topic_set_up;
  /** @param \scfr\main\controller\Topic */
  private $topic;

  /**
  * Constructor
  *
  * @param \phpbb\template\template             $template          Template object
  * @param \phpbb\user   $user             User object
  * @param \phpbb\db\driver\driver_interface   $db             Database object
  * @access public
  */
  public function __construct( \phpbb\template\template $template, \phpbb\user $user, \phpbb\db\driver\driver_interface $db) {
    $this->template = $template;
    $this->user = $user;
    $this->db = $db;
    $this->topic = new \scfr\sibylla\controller\Topic($db, $template);
    $this->forum = new \scfr\sibylla\controller\Forum($db, $template);
  }

  static public function getSubscribedEvents()
  {
    return array(
      'core.page_header_after' => 'set_common',
      'core.viewforum_modify_topicrow' => 'topic_row',
      'core.viewtopic_modify_post_row' => 'post_row',
      'core.viewforum_get_topic_ids_data' => 'forum_topic_row',
      'core.viewforum_topic_row_after' => 'topic_row_after',
      'core.display_forums_modify_template_vars' => 'forums_row',
    );
  }

  public function set_common($event) {
    $is_juliet = false;
    $ju = $this->db->sql_query('SELECT id, id_forum FROM star_fleet WHERE id_forum = "'.$this->user->data['user_id'].'" ');
    $raw = $this->db->sql_fetchrow($ju);
    if(isset($raw['id']) && $raw['id_forum'] > 1) {$is_juliet = true;}

    $tpl = array(
      'S_USER_IS_JULIET'		=> $is_juliet,
    );
    $this->template->assign_vars($tpl);
  }

  public function post_row($event) {
    // One time fire for topic inside sibylla
    if($this->forum->is_sibylla()) $this->topic->handleOneTimeFire($event);

    // Rank & group for every post of every topics
    $this->topic->set_ju_rank_and_dept($event);

    // Custom parsing for ju-bbcode inside sibylla topics.
    if($this->forum->is_sibylla(true)) {
      $this->topic->custom_parsing($event);
    }
  }

  public function forums_row($event) {
    // forum set_globals hasn't been called yet.
    $this->forum->squad_forum($event);
  }

  public function forum_topic_row($event) {
    $this->forum->set_globals($event);
  }

  public function topic_row($event) {
    if($this->forum->is_sibylla()) {
      if($this->topic->set_ju_dept($event))
      $this->forum->set_has_dpt();
    }
  }

  public function topic_row_after($event) {
    if($this->forum->is_sibylla() && $this->forum->has_departments())
    $this->forum->sort_departments($event);
  }

}
?>

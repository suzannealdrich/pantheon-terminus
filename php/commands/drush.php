<?php

use Terminus\Dispatcher;
use Terminus\Utils;
use Terminus\CommandWithSSH;
use Terminus\Models\Collections\Sites;
use Terminus\Helpers\Input;


class Drush_Command extends CommandWithSSH {
  /**
   * Name of client that command will be run on server via
   */
  protected $client = 'Drush';

  /**
   * A hash of commands which do not work in Terminus
   * The key is the drush command
   * The value is the Terminus equivalent, blank if DNE
   */
  protected $unavailable_commands = array(
    'sql-connect' => 'site connection-info --field=mysql_connection',
    'sql-sync'    => '',
  );

  /**
   * Invoke `drush` commands on a Pantheon development site
   *
   * <commands>...
   * : The Drush commands you intend to run.
   *
   * [--<flag>=<value>]
   * : Additional Drush flag(s) to pass in to the command.
   *
   * [--site=<site>]
   * : The name (DNS shortname) of your site on Pantheon.
   *
   * [--env=<environment>]
   * : Your Pantheon environment. Default: dev
   *
   */
  public function __invoke($args, $assoc_args) {
    $command = implode($args, ' ');
    $this->checkCommand($command);

    $sites              = new Sites();
    $assoc_args['site'] = Input::sitename($assoc_args);
    $site               = $sites->get($assoc_args['site']);
    if (!$site) {
      $this->failure('Command could not be completed. Unknown site specified.');
    }
    $assoc_args['env'] = $environment = Input::env($assoc_args);
    $server = $this->getAppserverInfo(
      array('site' => $site->get('id'), 'environment' => $environment)
    );

    # Sanitize assoc args so we don't try to pass our own flags.
    # TODO: DRY this out?
    if (isset($assoc_args['site'])) {
      unset($assoc_args['site']);
    }
    if (isset($assoc_args['env'])) {
      unset($assoc_args['env']);
    }

    # Create user-friendly output
    $flags = '';
    foreach ( $assoc_args as $k => $v ) {
      if (isset($v) && (string) $v != '') {
        $flags .= "--$k=$v ";
      }
      else {
        $flags .= "--$k ";
      }
    }
    if (in_array(Terminus::getConfig('format'), array('bash', 'json', 'silent'))) {
      $assoc_args['pipe'] = 1;
    }
    $this->log()->info(
      "Running drush {cmd} {flags} on {site}-{env}",
      array(
        'cmd' => $command,
        'flags' => $flags,
        'site' => $site->get('name'),
        'env' => $environment
      )
    );
    $result = $this->sendCommand($server, 'drush', $args, $assoc_args);
    if (Terminus::getConfig('format') != 'normal') {
      $this->output()->outputRecordList($result);
    }
  }
}

Terminus::addCommand( 'drush', 'Drush_Command' );

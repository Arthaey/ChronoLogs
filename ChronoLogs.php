<?php

class ChronoLogs {

  public static function onParserFirstCallInit( $parser ) {
    $parser->setFunctionHook( 'chronologs', 'ChronoLogs::parserFunction' );
  }

  public static function parserFunction( $parser ) {
    $params = func_get_args();
    array_shift( $params ); // first arg is $parser, drop it

    $category = trim( array_shift( $params ) );
    if ( !$category ) {
      return 'Usage: <code>{{#ChronoLogs:CategoryName}}</code>';
    }

    $categoryTitle = Title::newFromText( "Category:{$category}" );
    if ( !$categoryTitle || !$categoryTitle->isKnown() ) {
      return "Could not find [[:Category:{$category}]].";
    }

    $logs = ChronoLogs::getLogs( $categoryTitle );
    $logsByDate = [];

    foreach ( $logs as $log ) {
      $t = Title::newFromText( $log->page_title );
      $subpages = $t->getSubpages();

      if( !empty( $subpages ) ) {
          while( $subpages->valid() ) {
              $subpage = $subpages->current();
              $logDate = ChronoLogs::getLogDate( $subpage );
              $logsByDate[$logDate][] = $subpage;
              $subpages->next();
          }
      }
    }

    ksort($logsByDate);

    return ChronoLogs::logsToHTML( $logsByDate, $parser );
  }

  private static function getLogs( $categoryTitle ) {
    $dbr = wfGetDB( DB_REPLICA );

    $tables = [ 'page', 'categorylinks' ];
    $fields = [ 'page_id', 'page_title', 'cl_to', 'cl_from' ];
    $joins = [
      'categorylinks' => [ 'JOIN', 'cl_from = page_id' ]
    ];
    $where = [
      'cl_to' => $categoryTitle->getDBkey(),
      'cl_type' => 'page'
    ];
    $options = [
      'ORDER BY' => 'cl_sortkey',
      'USE INDEX' => [ 'categorylinks' => 'cl_sortkey' ]
    ];

    return $dbr->select( $tables, $fields, $where, __METHOD__, $options, $joins );
  }

  private static function getLogDate( $title ) {
    $titleParts = explode( '/', $title->getText() );
    return $titleParts[ count($titleParts) - 1 ];
  }

  private static function logsToHTML( $logsByDate, $parser ) {
    $html = '';

    foreach ( $logsByDate as $logDate => $logPages ) {
      foreach ( $logPages as $logPage ) {
        $fullPage = new WikiPage( $logPage );
        $wikitext = $fullPage->getContent()->getWikitextForTransclusion();

        $html .= "\n\n### [[{$logPage->getText()}]]\n\n";

        $html .= $parser->recursivePreprocess(
          "{{#vardefine:today|$logDate}}\n" .
          "{{#vardefine:isTranscluded|yes}}\n" .
          "\n\n" . $wikitext . "\n\n"
        );
      }
    }

    return $html;
  }

  private static function debug( $obj ) {
    return '<pre>' . var_export($obj, true) . '</pre>';
  }

}

?>

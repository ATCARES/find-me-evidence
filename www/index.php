<?php
error_reporting ( E_ERROR );

include_once ('config.php');
include_once ('functions.php');

if (isset ( $_GET ["q"] ) and $_GET ["q"] != "") {
	$user_query = $_GET ["q"];
	
	$selected_category = $_GET ["category"];
	if ($selected_category == "")
		$selected_category = "all"; // set default value if missing
	
	$offset = $_GET ["offset"];
	if ($offset == "")
		$offset = 0; // set default value if missing
	
	$xml = query_solr ( $user_query, $selected_category, $max_rows, $offset );
	
	if ($xml->result ["numFound"] == 0) {
		$corrected_query = xpath ( $xml, "//str[@name='collation']", false );
		if ($corrected_query != "") {
			print "<!-- Collation: $corrected_query -->";
			$xml = query_solr ( $corrected_query, $selected_category, $max_rows, $offset ); // re-run query with suggested collation
			$query_results_are_based_on_automatic_correction = true;
		}
	}
}

$page_title = "FindMeEvidence";
if ($user_query != "") {
	$page_title .= ": Search results for " . htmlspecialchars ( urldecode ( $user_query ) );
}

$http_lang = substr($_SERVER["HTTP_ACCEPT_LANGUAGE"], 0, 2);
switch ($http_lang) {
    case 'de':
        $show_de = true;
        break;
    case 'es':
        $show_es = true;
        break;
    default:
        $show_es = true;
        $show_de = true;
}

?>
<!DOCTYPE html>
<html>
<head>
<title><?php print $page_title ?></title>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="css/themes/default/jquery.mobile-1.3.2.min.css">
<link rel="stylesheet" href="_assets/css/jqm-demos.css">
<link rel="shortcut icon" href="images/favicon.ico">
<link rel="stylesheet" href="http://fonts.googleapis.com/css?family=Open+Sans:300,400,700">
<link href="bricoleur.css" rel="stylesheet" type="text/css">
<script src="js/jquery.js"></script>
<script src="_assets/js/index.js"></script>
<script src="js/jquery.mobile-1.3.2.min.js"></script>
<script>       
        //http://techniqzone.blogspot.co.at/2013/05/get-cursor-position-in-textarea-jquery.html
        (function ($, undefined) {
        $.fn.getCursorPosition = function() {
        var el = $(this).get(0);
        var pos = 0;
        if('selectionStart' in el) {
            pos = el.selectionStart;
        } else if('selection' in document) {
            el.focus();
            var Sel = document.selection.createRange();
            var SelLength = document.selection.createRange().text.length;
            Sel.moveStart('character', -el.value.length);
            pos = Sel.text.length - SelLength;
        }
        return pos;
        }
        })(jQuery);
        
	// Delay function, used for having a short delay after user typed something before initiating request to autocomplete service
	var delay = (function(){
		  var timer = 0;
		  return function(callback, ms){
		    clearTimeout (timer);
		    timer = setTimeout(callback, ms);
		  };
		})();

	// Escape HTML characters -- TODO: This does not seem to work (input not changed)
	function escapeHtml(text) {
		  return text
		      .replace("&", "&amp;")
		      .replace('"', "&quot;")
		      .replace("'", "&#039;");
		}
        
        function escapeHtmlAndRemove(text) {
		  return text
		      .replace("&", "&amp;")
		      .replace('"', "&quot;")
		      .replace("'", "&#039;")
                      .replace("<ins>", "")
                      .replace("</ins>", "");
		}

	// Update the autocomplete list
	function updateAutocomplete() {
        var $ul = $('#autocomplete'),   
            $input = $('#q'),
            value = $input.val(),
            html = "",
            language = "";
    
        $ul.html("");
    
        $("input:radio[name=language]:checked").each(function(){
            language = $(this).val();
        });
        
        if ( value && value.length > 3 ) {
            //$ul.html( "<li><div class='ui-loader'><span class='ui-icon ui-icon-loading'></span></div></li>" );
            
            if ( language !== "")
            $.ajax({
            	url: "translate.php",
				dataType: "json",
				crossDomain: false,
                data: {
                    q: $input.val(), l: language, p: $input.getCursorPosition()
                }
            })
            .then( function ( response ) {
            	$ul.html( "" );
            	$ul.listview( "refresh" );
                $.each( response, function ( i, val ) {
                    if (i === 0 && val !== "") {
                        if (language ==="ger") {
                            html += '<li onclick=\'$("#q").val("' + escapeHtmlAndRemove(val) + '"); pubmedAutocomplete(true); $("#q").focus();\'><img src="images/gb.png" alt="english" class="ui-li-icon ui-li-thumb">' + val + ' <small>(suggested translation)</small></li>';
                        }
                        else if (language ==="esp") {
                            html += '<li onclick=\'$("#q").val("' + escapeHtmlAndRemove(val) + '"); pubmedAutocomplete(true); $("#q").focus();\'><img src="images/esp.png" alt="spanish" class="ui-li-icon ui-li-thumb">' + val + ' <small>(suggested translation)</small></li>';
                        }
                    } else if ( i !== 0 && val !== ""){                        
                        html += '<li onclick=\'$("#q").val("' + escapeHtml(val) + '"); $("#search_form").submit();\'>' + val + '</li>';
                    }
                });                            
                $ul.html( html );
                $ul.listview( "refresh" );
                $ul.trigger( "updatelayout");
            });
            
            pubmedAutocomplete();
            
            $input.focus();
        }	   
	}
        
        /**
        * calls PubMed Autocomplete

         * @param {type} deleteList delete list
         * @returns {Boolean}         */
        function pubmedAutocomplete(deleteList) {
            deleteList = deleteList || false;
            if(deleteList) {
                $('#autocomplete').html("");
            }    
            $.getJSON("http://preview.ncbi.nlm.nih.gov/portal/utils/autocomp.fcgi?dict=pm_related_queries_2&callback=?&q="+ encodeURIComponent($("#q").val()), NSuggest_CreateData);
            return false;
        }
            
        function NSuggest_CreateData(q, data) {
            var max = Math.min(5,data.length-1),
                $ul = $('#autocomplete');
            
            for (var i = 0; i < max; ++i) {
                $ul.append('<li onclick=\'$("#q").val("' + escapeHtml(data[i]) + '"); $("#search_form").submit();\'>' + data[i] + '</li>');
            }
            if (max === 0) {
                $ul.append('<li>no autocompletion available</li>');
            }
            $ul.listview( "refresh" );
            $ul.trigger( "updatelayout");
        }
</script>
</head>
<body>
	<div data-role="page" id="main" data-theme="d">
            
		<!--  
		<div data-role="header">
			<h3>FindMeEvidence</h3>
			<a
				href="https://code.google.com/p/bricoleur-fast-medical-search/w/list"
				data-icon="info" data-iconpos="notext" data-rel="dialog"
				data-transition="fade">Help</a>
		</div>
		-->
                
                <!--If you need to to create a header that doesn't follow the default configuration, 
                simply wrap your custom styled markup in a container div inside the header container 
                and the plugin won't apply the automatic button logic so you can write custom styles 
                for laying out the content in your header.-->

                <div data-role="header" class="jqm-header" style="text-align: center; padding: 3px">
                    <div><a href="index.php"><img src="images/findmeevidence-logo.png" alt="FindMeEvidence" /></a></div>
                </div>
		
		<div data-role="content">
			<div style="padding-top: 10px; padding-bottom: 0px">
				<?php if ($user_query != "") : // if a query was entered ?>
				
				<!-- BEGIN: Search bar with existing results -->
				<form action="index.php" method="get" id="search_form"
					data-ajax="false">
					<!--<label for="search-input">Search input:</label>-->
					<input type="search" name="q" id="q" data-theme="e"
						autocomplete="off" placeholder="Enter query..."
						onkeyup="delay(function(){updateAutocomplete();}, 600 );"
						value="<?php print htmlspecialchars(urldecode($user_query))?>" />
                                       
                                        <ul id="autocomplete" data-role="listview" data-inset="true"></ul>
                                        
                                        <fieldset data-role="controlgroup" data-type="horizontal" data-mini="true">
                                            <?php if($show_de): ?>
                                                <input type="radio" name="language" id="langger" value="ger"
                                                   onclick="updateAutocomplete();" data-theme="c"/>
                                                <label for="langger">suggest german to english</label>
                                            <?php endif; ?>
                                            <?php if($show_es): ?>
                                                <input type="radio" name="language" id="langesp" value="esp"
                                                   onclick="updateAutocomplete();" data-theme="c"/>
                                                <label for="langesp">suggest spanish to english</label>
                                            <?php endif; ?>
                                        </fieldset>
                                        
					<fieldset data-role="controlgroup" data-type="horizontal"
						data-mini="true" style="border:none">
						<select name="category" id="category"
							onchange='$("#search_form").submit();'>

							<?php
					if (($_GET ["category"] == "all") OR (isset($_GET ["category"]) == false)) {
						print ('<option value="all" selected="selected">Filter results (' . $xml->result ["numFound"] . ')</option>') ;
					} else {
						print ('<option value="all">Show all</option>') ;
					}
					
					foreach ( $categories as $category => $category_for_solr ) {
						print ("<option value=\"$category\"") ;
						if ($selected_category == $category) {
							print (' selected="selected"') ;
						}
						print (">") ;
						print ($category . " (" . get_facet_count ( $xml, $category ) . ")</option>") ;
					}
					?>
						</select>
					</fieldset>
                                        
				</form>
				<!-- END: Search bar with existing results -->

			</div>
			<?php
					if ($query_results_are_based_on_automatic_correction == true) {
						print ("<div style='padding-bottom:1em'><p>Your original query <em>$user_query</em> did not yield any results. Showing results for <em><b>$corrected_query</b></em> instead.</p></div>\n") ;
					}
					?> 
			<div>

				<!-- BEGIN: List of results -->
				<ul data-role="listview" data-inset="true">
					
					<?php
					$count = 0;
					foreach ( $xml->result->doc as $doc ) : // Iterate through documents in result set
						$id = xpath ( $doc, "str[@name='id']" );
                                                $suspicious = (string)xpath ( $doc, "bool[@name='suspicious']" );
                                                $oa = (string)xpath ( $doc, "bool[@name='oa']" ); ?>
                                                
						<li <?php if($suspicious === "true") print ('data-icon="alert"'); ?>>
                                                        <?php
							if (substr ( $id, 0, 35 ) == "http://www.ncbi.nlm.nih.gov/pubmed/")
								print ("<a href=\"show.php?id=" . urlencode ( $id ) . "\">") ;
							else
								print (writeRedirect($id)) ;
                                                        ?>
                                                        
                                                        <?php if ($suspicious === "true"): ?>
                                                        <p class="ui-li-aside"><span style="font-size : small; color : #FF0000;">quality rating not available or low</span></p>
                                                        <?php endif; ?>
                                                        
							<h3>
									<?php print xpath($doc, "arr[@name='title']/str"); ?>
                                                                        <?php if ($oa === "true"): ?>
                                                                            <img src="images/OA-icon.gif" alt=OA />
                                                                        <?php endif; ?>
								</h3>                                                        
							<p>
								<span class="data_source_name"><?php print xpath($doc, "str[@name='data_source_name']"); ?>
									</span> 
									<?php
										// Show dateCreated for PubMed entriesS
										if (  substr(xpath($doc, "str[@name='data_source_name']"), 0, 6) == "PubMed"  ) {
											$date_created = substr ( xpath ( $doc, "date[@name='dateCreated']" ), 0, 10 );
											if ($date_created != "")
												print ("<span class=\"publication_date\"> &nbsp;|&nbsp;" . $date_created . "</span>");
											}
									?>
							</p> <?php if(xpath($doc, "str[@name='key_assertion']")): ?>
								<p class="conclusion">
									<?php print xpath($doc, "str[@name='key_assertion']")?>
								</p> <?php elseif($snippets = xpath($doc, "//lst[@name='highlighting']/lst[@name='${id}']/arr[@name='body']/str", true)): ?>
								<p class="text_snippet">
									<?php print("... " . implode(" ... ", $snippets) . " ..."); ?>
								</p> <?php endif; ?>
                                                        <?php print("</a>"); ?>
						</li>
					<?php	
					endforeach;
					?>
				</ul>
				<!-- END: List of results -->
				

				<?php
					// If no results were found
					if ($xml->result ["numFound"] == 0)
						print ("<p>No results found.</p>") ; // if a query was entered and no results were found
							                                                                      
					?>
				
				<p style="text-align: center">
					<?php 
						// If pagination of results is is necessary
						if ($xml->result ["numFound"] > ($offset + $max_rows)) {
							print "<a href=\"index.php?q=" . $user_query . "&category=" . $selected_category . "&offset=" . ($offset + $max_rows) . "\" data-role=\"button\" data-inline=\"true\">Show more results</a>";
						}
					?>
					<a
						href="http://www.google.com/search?q=<?php print htmlspecialchars($user_query)?>"
						data-role="button" data-inline="true" target="blank">Try this
						search in Google</a> <a
						href="http://www.ncbi.nlm.nih.gov/pubmed/?term=<?php print htmlspecialchars($user_query)?>"
						data-role="button" data-inline="true" target="blank">Try this
						search in PubMed</a> 
				</p>
			</div>

			<?php else: // if no query was entered, show default startup search bar ?>
			
				<!-- BEGIN: Default startup search bar -->
			<form action="index.php" method="get" id="search_form"
				data-ajax="false">
				<!--<label for="search-input">Search input:</label>-->
				<input type="search" name="q" id="q" data-theme="e"
					autocomplete="off" placeholder="Enter query..."
					onkeyup="delay(function(){updateAutocomplete();}, 600 );"
					value="<?php print htmlspecialchars(urldecode($user_query))?>" />
                                
				<ul id="autocomplete" data-role="listview" data-inset="true"></ul>                                                           
                                
                                <fieldset data-role="controlgroup" data-type="horizontal" data-mini="true">
                                    <?php if($show_de): ?>
                                        <input type="radio" name="language" id="langger" value="ger"
                                            onclick="updateAutocomplete();" data-theme="c"/>
                                        <label for="langger">suggest german to english</label>
                                    <?php endif; ?>
                                    <?php if($show_es): ?>
                                        <input type="radio" name="language" id="langesp" value="esp"
                                            onclick="updateAutocomplete();" data-theme="c"/>
                                        <label for="langesp">suggest spanish to english</label>
                                    <?php endif; ?>   
                                </fieldset>
                                
			</form>
			<script type="text/javascript">
				$("#main").on("pageshow" , function() {
					$('#q').focus();
					});
			</script>
			<p>Welcome to FindMeEvidence, an efficient search engine
				for rapidly reviewing current, openly available medical evidence.</p>
			
			<p style="color: grey; padding-top: 25px">This service is currently based on information from the following sources:
				<ul style="color: grey">
					<li>Clinically relevant journals from PubMed</li>
					<li>Medscape.com</li>
					<li>Merck Manuals</li>
					<li>Guidelines.gov</li>
					<li>ATTRACT (an evidence-based medical question answering service)</li>
					<li>BestBETs (another evidence-based medical question answering service)</li>
					<li>Medical and pharmacological Wikipedia articles</li>
				</ul>
			</p>
			<!-- END: Default startup search bar -->
		</div>
			<?php endif; ?>
		</div>
	<div data-role="footer">
		<h4>The FindMeEvidence service comes without any warranty. Visit <a href="https://github.com/matthias-samwald/find-me-evidence">project website</a> for more information.</h4>
	</div>
</body>
</html>

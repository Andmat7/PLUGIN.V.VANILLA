<?php if (!defined('APPLICATION')) exit();
/*
Copyright 2008, 2009 Vanilla Forums Inc.
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Vanilla Forums Inc. at support [at] vanillaforums [dot] com
*/

// Define the plugin:
$PluginInfo['Votacion'] = array(
   'Name' => 'Votacion',
   'Description' => 'Este plugin permite generar preguntas y un sistema de puntaje para éstas',
   'Version' => '1.1.0',
   'RequiredApplications' => FALSE,
   'RequiredTheme' => FALSE,
   'RequiredPlugins' => FALSE,
   'SettingsUrl' => '/dashboard/plugin/flagging',
   'SettingsPermission' => 'Garden.Settings.Manage',
   'HasLocale' => TRUE,
   'RegisterPermissions' => array('Plugins.Flagging.Notify'),
   'Author' => "Tim Gunter",
   'AuthorEmail' => 'tim@vanillaforums.com',
   'AuthorUrl' => 'http://www.vanillaforums.com'
);
//Array de Configuración de puntos.
class VotacionPlugin extends Gdn_Plugin {

private $PuntosConfig=array(
    'Puntosiniciales'=>10,//Puntos con los que inicia un usuario recien llegado.
    'CostodePregunta'=>5,//Puntos que cuesta realizar una pregunta.
    'PuntosporRespuesta'=>3,//Pts qu se gana por responder una pregunta.
    'PuntosporVoto'=>1,//Puntos ganados al dar un voto positivo.
    'PuntosPostNormal'=>1,//Puntos que se ganan al escribir un post normal.
    'PuntosComentNormal'=>1//puntos que se ganan al escribir un comentario a un post normal.
);
    /**
	 *Herramimienta de Administracion del plugin voting.
	 */
   public function Base_GetAppSettingsMenuItems_Handler(&$Sender) {
      $Menu = &$Sender->EventArguments['SideMenu'];
      $Menu->AddItem('Forum', T('Forum'));
      $Menu->AddLink('Forum', T('Votacion'), 'settings/votacion', 'Garden.Settings.Manage');
   }

   public function SettingsController_Votacion_Create($Sender) {
      $Sender->Permission('Garden.Settings.Manage');
      $Conf = new ConfigurationModule($Sender);
		$Conf->Initialize(array(
			'Plugins.Voting.ModThreshold1' => array('Type' => 'int', 'Control' => 'TextBox', 'Default' => -10, 'Description' => 'Cantidad de votos que marca el post para moderación'),
			'Plugins.Voting.ModThreshold2' => array('Type' => 'int', 'Control' => 'TextBox', 'Default' => -20, 'Description' => 'Cantidad de votos que remueve el post para la cola de moderación .')
		));

     $Sender->AddSideMenu('dashboard/settings/votacion');
     $Sender->SetData('Title', T('Configuración de Votos'));
     $Sender->ConfigurationModule = $Conf;
     $Conf->RenderAll();
   }

   public function SettingsController_ToggleVotacion_Create($Sender) {
      $Sender->Permission('Garden.Settings.Manage');
      if (Gdn::Session()->ValidateTransientKey(GetValue(0, $Sender->RequestArgs)))
         SaveToConfig('Plugins.Votacion.Enabled', C('Plugins.Votacion.Enabled') ? FALSE : TRUE);

      Redirect('settings/votacion');
   }
	/**
	 * Add JS & CSS to the page.
	 */
   public function AddJsCss($Sender) {
//		if (!C('Plugins.Voting.Enabled'))
//			return;

      $Sender->AddCSSFile('votacion.css', 'plugins/Votacion');
		$Sender->AddJSFile('plugins/Votacion/votacion.js');
   }
   public function DiscussionsController_Render_Before($Sender) {
		$this->AddJsCss($Sender);
      $this->AddJsmensaje($Sender);
	}
   public function CategoriesController_Render_Before($Sender) {
      $this->AddJsCss($Sender);
   }
   public function DiscussionController_Render_Before($Sender) {
      $this->AddJsmensaje($Sender);
      $this->AddJsCss($Sender);
   }
   /*
    *Mensaje de consto de pregunta
    */
   public function AddJsmensaje($Sender){
       $PuntosporPregunta= GetValue('CostodePregunta', $this->PuntosConfig, array());
       $Sender->Head->AddString("
           <style type='text/css' media='screen'>
           </style>
           <script>
           $(document).ready(function(){
           $('#Form_Pregunta').click(function(){
               if (this.checked) alert('Elija esta opcion solo si tiene una pregunta bien definida, Costo de pregunta =".$PuntosporPregunta." ');
               else alert('checkbox desactivado')
           });});
           </script> ");

         }
/*
 *Crea los botones de "Estados" a la lista de Discusion(votos,follows,etv)
 */
   public function Base_BeforeDiscussionContent_Handler($Sender) {
//		if (!C('Plugins.Voting.Enabled'))
//			return;

		$Session = Gdn::Session();
		$Discussion = GetValue('Discussion', $Sender->EventArguments);
		// Answers
		$Css = 'StatBox AnswersBox';
		if ($Discussion->CountComments > 1)
			$Css .= ' HasAnswersBox';

		$CountVotes = 0;
		if (is_numeric($Discussion->Score)) // && $Discussion->Score > 0)
			$CountVotes = $Discussion->Score;

		if (!is_numeric($Discussion->CountBookmarks))
			$Discussion->CountBookmarks = 0;

		echo Wrap(
			// Anchor(
			Wrap(T('Comments')) . Gdn_Format::BigNumber($Discussion->CountComments - 1)
			// ,'/discussion/'.$Discussion->DiscussionID.'/'.Gdn_Format::Url($Discussion->Name).($Discussion->CountCommentWatch > 0 ? '/#Item_'.$Discussion->CountCommentWatch : '')
			// )
			, 'div', array('class' => $Css));

		// Views
		echo Wrap(
			// Anchor(
			Wrap(T('Views')) . Gdn_Format::BigNumber($Discussion->CountViews)
			// , '/discussion/'.$Discussion->DiscussionID.'/'.Gdn_Format::Url($Discussion->Name).($Discussion->CountCommentWatch > 0 ? '/#Item_'.$Discussion->CountCommentWatch : '')
			// )
			, 'div', array('class' => 'StatBox ViewsBox'));

		// Follows
		$Title = T($Discussion->Bookmarked == '1' ? 'Unbookmark' : 'Bookmark');
		if ($Session->IsValid()) {
			echo Wrap(Anchor(
				Wrap(T('Follows')) . Gdn_Format::BigNumber($Discussion->CountBookmarks),
				'/vanilla/discussion/bookmark/'.$Discussion->DiscussionID.'/'.$Session->TransientKey().'?Target='.urlencode($Sender->SelfUrl),
				'',
				array('title' => $Title)
			), 'div', array('class' => 'StatBox FollowsBox'));
		} else {
			echo Wrap(Wrap(T('Follows')) . $Discussion->CountBookmarks, 'div', array('class' => 'StatBox FollowsBox'));
		}

		// Votes
		if ($Session->IsValid()) {
			echo Wrap(Anchor(
				Wrap(T('Votes')) . Gdn_Format::BigNumber($CountVotes),
				'/vanilla/discussion/votediscussion/'.$Discussion->DiscussionID.'/'.$Session->TransientKey().'?Target='.urlencode($Sender->SelfUrl),
				'',
				array('title' => T('Vote'))
			), 'div', array('class' => 'StatBox VotesBox'));
		} else {
			echo Wrap(Wrap(T('Votes')) . $CountVotes, 'div', array('class' => 'StatBox VotesBox'));
		}
	}

    /**
     * Organiza los comentarios por popularidad si es necesario
    * @param CommentModel $CommentModel
	 */
   public function CommentModel_AfterConstruct_Handler($CommentModel) {
//		if (!C('Plugins.Voting.Enabled'))
//			return;

      $Sort = self::CommentSort();

      switch (strtolower($Sort)) {
         case 'date':
            $CommentModel->OrderBy('c.DateInserted');
            break;
         case 'popular':
         default:
            $CommentModel->OrderBy(array('coalesce(c.Score, 0) desc', 'c.CommentID'));
            break;
      }
   }

   protected static $_CommentSort;
   public static function CommentSort() {
//		if (!C('Plugins.Voting.Enabled'))
//			return;

      if (self::$_CommentSort)
         return self::$_CommentSort;

      $Sort = GetIncomingValue('Sort', '');
      if (Gdn::Session()->IsValid()) {
         if ($Sort == '') {
            // No sort was specified so grab it from the user's preferences.
            $Sort = Gdn::Session()->GetPreference('Plugins.Voting.CommentSort', 'popular');
         } else {
            // Save the sort to the user's preferences.
            Gdn::Session()->SetPreference('Plugins.Voting.CommentSort', $Sort == 'popular' ? '' : $Sort);
         }
      }

      if (!in_array($Sort, array('popular', 'date')))
         $Sort = 'popular';
      self::$_CommentSort = $Sort;
      return $Sort;
   }
	/**
	 * Agregar tabs despues del primer comentario.
	 */
   public function DiscussionController_BeforeCommentDisplay_Handler($Sender) {
//		if (!C('Plugins.Voting.Enabled'))
//			return;

		$AnswerCount = $Sender->Discussion->CountComments - 1;
		$Type = GetValue('Type', $Sender->EventArguments, 'Comment');
		if ($Type == 'Comment' && !GetValue('VoteHeaderWritten', $Sender)) { //$Type != 'Comment' && $AnswerCount > 0) {
		?>
		<li>
			<div class="Tabs DiscussionTabs AnswerTabs">
			<?php
			echo
				Wrap($AnswerCount.' '.Plural($AnswerCount, 'Comment', 'Comments'), 'strong');
				echo ' sorted by
				<ul>
					<li'.(self::CommentSort() == 'popular' ? ' class="Active"' : '').'>'.Anchor('Votes', Url('?Sort=popular', TRUE), '', array('rel' => 'nofollow')).'</li>
					<li'.(self::CommentSort() == 'date' ? ' class="Active"' : '').'>'.Anchor('Date Added', Url('?Sort=date', TRUE), '', array('rel' => 'nofollow')).'</li>
				</ul>';
			?>
			</div>
		</li>
		<?php
      $Sender->VoteHeaderWritten = TRUE;
		}
	}
   public function DiscussionController_BeforeCommentMeta_Handler($Sender) {
//		if (!C('Plugins.Voting.Enabled'))
//			return;

			$Object = GetValue('Object', $Sender->EventArguments);
            $DiscussionID=$Object->DiscussionID;
			$ID = $Sender->EventArguments['Type'] == 'Discussion' ? $Object->DiscussionID : $Object->CommentID;
            $pregunta=$Sender->DiscussionModel->SQL
                     ->Select('answer')
                     ->From('Discussion')
                     ->Where('DiscussionID', $DiscussionID)
                     ->Get()->Value('answer');
            if($pregunta == 1){

            echo '<span class="Votes">';
			$Session = Gdn::Session();
			$VoteType = $Sender->EventArguments['Type'] == 'Discussion' ? 'votediscussion' : 'votecomment';
			$CssClass = '';
			$VoteUpUrl = '/discussion/'.$VoteType.'/'.$ID.'/voteup/'.$Session->TransientKey().'/';
			$VoteDownUrl = '/discussion/'.$VoteType.'/'.$ID.'/votedown/'.$Session->TransientKey().'/';
			if (!$Session->IsValid()) {
				$VoteUpUrl = Gdn::Authenticator()->SignInUrl($Sender->SelfUrl);
				$VoteDownUrl = $VoteUpUrl;
				$CssClass = ' SignInPopup';
			}
			echo Anchor(Wrap(Wrap('Vote Up', 'i'), 'i', array('class' => 'ArrowSprite SpriteUp', 'rel' => 'nofollow')), $VoteUpUrl, 'VoteUp'.$CssClass);
			echo Wrap(StringIsNullOrEmpty($Object->Score) ? '0' : Gdn_Format::BigNumber($Object->Score));
			echo Anchor(Wrap(Wrap('Vote Down', 'i'), 'i', array('class' => 'ArrowSprite SpriteDown', 'rel' => 'nofollow')), $VoteDownUrl, 'VoteDown'.$CssClass);
            echo '</span>';
            }
	}
   /**
   /**
    * Incrementar/Decrementar la puntuación de los comentarios.
    */
   public function DiscussionController_VoteComment_Create($Sender) {
//		if (!C('Plugins.Voting.Enabled'))
//			return;
      $PuntosporVoto=1;
      $PuntosMaxComent=10;
      $CommentID = GetValue(0, $Sender->RequestArgs, 0);
      $VoteType = GetValue(1, $Sender->RequestArgs);
      $TransientKey = GetValue(2, $Sender->RequestArgs);
      $Session = Gdn::Session();
      $FinalVote = 0;
      $Total = 0;
      if ($Session->IsValid() && $Session->ValidateTransientKey($TransientKey) && $CommentID > 0) {
         $CommentModel = new CommentModel();
         $OldUserVote = $CommentModel->GetUserScore($CommentID, $Session->UserID);
         //Busca el usuario que creo la respuesta.
         $SQL=$CommentModel->SQL;
         $CommentUser=$SQL->Select('InsertUserID')
                         ->From('Comment')
                         ->Where('CommentID',$CommentID)
                         ->Get()->Value('InsertUserID');

         $NewUserVote = $VoteType == 'voteup' ? 1 : -1;
         $FinalVote = intval($OldUserVote) + intval($NewUserVote);
         // Permite a los administradores aumentar los votos indefinidamente
         $AllowVote = $Session->CheckPermission('Garden.Moderation.Manage');
         //Solo permite aumentar o disminuir 1 voto a los otros usuarios
         if (!$AllowVote)
            $AllowVote = $FinalVote > -2 && $FinalVote < 2;

         if ($AllowVote)
         {
             //si el voto es positivo entonces comprueba cuantos votos hay.
             if ($NewUserVote == 1)
             {
                 $PuntosdeComentarioAntiguo=$SQL->Select('Score')
                             ->From('CommentPuntos')
                             ->Where('UserID',$CommentUser)
                             ->Get()->Value('Score');
                 $UserID=$Session->UserID;
                 if(!isset($PuntosdeComentarioAntiguo))$PuntosdeComentarioAntiguo=0;
                 if(($PuntosdeComentarioAntiguo <= $PuntosMaxComent) && ($CommentUser!=$Session->UserID)){
                     //Después de verificar si es menor al máximo permitido
                     //Suma los puntos puntos a la tabla "CommentPuntos"
                     $PtsComentfinal=$PuntosdeComentarioAntiguo+$PuntosporVoto;
                     $TotaL=$SQL->Replace('CommentPuntos',
                                array('Score' => $PtsComentfinal),
                                array('CommentID' => $CommentID, 'UserID' => $CommentUser)
                            );
                     //suma los puntos al usuario
                     $PuntosAntiguos=$SQL->Select('score')
                                 ->From('User')
                                 ->Where('UserID',$CommentUser)
                                 ->Get()->Value('score');

                     $PuntosFinal=$score+$PuntosporVoto;
                            $SQL->Update("User")
                                ->Where('UserID',$CommentUser)
                                ->Set('score',$PuntosFinal, FALSE)
                                ->Put();
                 }
             }
            $Total = $CommentModel->SetUserScore($CommentID, $Session->UserID, $FinalVote);
         }
         // Move the comment into or out of moderation.
         if (class_exists('LogModel')) {
            $Moderate = FALSE;

            if ($Total <= C('Plugins.Voting.ModThreshold1', -10)) {
               $LogOptions = array('GroupBy' => array('RecordID'));
               // Get the comment row.
               $Data = $CommentModel->GetID($CommentID, DATASET_TYPE_ARRAY);
               if ($Data) {
                  // Get the users that voted the comment down.
                  $OtherUserIDs = $CommentModel->SQL
                     ->Select('UserID')
                     ->From('UserComment')
                     ->Where('CommentID', $CommentID)
                     ->Where('Score <', 0)
                     ->Get()->ResultArray();
                  $OtherUserIDs = ConsolidateArrayValuesByKey($OtherUserIDs, 'UserID');
                  $LogOptions['OtherUserIDs'] = $OtherUserIDs;

                  // Add the comment to moderation.
                  if ($Total > C('Plugins.Voting.ModThreshold2', -20))
                     LogModel::Insert('Moderate', 'Comment', $Data, $LogOptions);
               }
               $Moderate = TRUE;
            }
            if ($Total <= C('Plugins.Voting.ModThreshold2', -20)) {
               // Remove the comment.
               $CommentModel->Delete($CommentID, array('Log' => 'Moderate'));

               $Sender->InformMessage(sprintf(T('The %s has been removed for moderation.'), T('comment')));
            } elseif ($Moderate) {
               $Sender->InformMessage(sprintf(T('The %s has been flagged for moderation.'), T('comment')));
            }
         }
      }
      $Sender->DeliveryType(DELIVERY_TYPE_BOOL);
      $Sender->SetJson('TotalScore', $Total);
      $Sender->SetJson('FinalVote', $FinalVote);
      $Sender->Render();
   }

   /**
    * Incrementar/Decrementar la puntuación de las discusiones.
    */
   public function DiscussionController_VoteDiscussion_Create($Sender) {
//		if (!C('Plugins.Voting.Enabled'))
//			return;

      $DiscussionID = GetValue(0, $Sender->RequestArgs, 0);
      $TransientKey = GetValue(1, $Sender->RequestArgs);
      $VoteType = FALSE;
      if ($TransientKey == 'voteup' || $TransientKey == 'votedown') {
         $VoteType = $TransientKey;
         $TransientKey = GetValue(2, $Sender->RequestArgs);
      }
      $Session = Gdn::Session();
      $NewUserVote = 0;
      $Total = 0;
      if ($Session->IsValid() && $Session->ValidateTransientKey($TransientKey) && $DiscussionID > 0) {
         $DiscussionModel = new DiscussionModel();
         $OldUserVote = $DiscussionModel->GetUserScore($DiscussionID, $Session->UserID);

         if ($VoteType == 'voteup')
            $NewUserVote = 1;
         else if ($VoteType == 'votedown')
            $NewUserVote = -1;
         else
            $NewUserVote = $OldUserVote == 1 ? -1 : 1;

         $FinalVote = intval($OldUserVote) + intval($NewUserVote);
         // Allow admins to vote unlimited.
         $AllowVote = $Session->CheckPermission('Garden.Moderation.Manage');
         // Only allow users to vote up or down by 1.
         if (!$AllowVote)
            $AllowVote = $FinalVote > -2 && $FinalVote < 2;

         if ($AllowVote) {
            $Total = $DiscussionModel->SetUserScore($DiscussionID, $Session->UserID, $FinalVote);
         } else {
				$Discussion = $DiscussionModel->GetID($DiscussionID);
				$Total = GetValue('Score', $Discussion, 0);
				$FinalVote = $OldUserVote;
			}

         // Move the comment into or out of moderation.
         if (class_exists('LogModel')) {
            $Moderate = FALSE;

            if ($Total <= C('Plugins.Voting.ModThreshold1', -10)) {
               $LogOptions = array('GroupBy' => array('RecordID'));
               // Get the comment row.
               if (isset($Discussion))
                  $Data = (array)$Discussion;
               else
                  $Data = (array)$DiscussionModel->GetID($DiscussionID);
               if ($Data) {
                  // Get the users that voted the comment down.
                  $OtherUserIDs = $DiscussionModel->SQL
                     ->Select('UserID')
                     ->From('UserComment')
                     ->Where('CommentID', $DiscussionID)
                     ->Where('Score <', 0)
                     ->Get()->ResultArray();
                  $OtherUserIDs = ConsolidateArrayValuesByKey($OtherUserIDs, 'UserID');
                  $LogOptions['OtherUserIDs'] = $OtherUserIDs;

                  // Add the comment to moderation.
                  if ($Total > C('Plugins.Voting.ModThreshold2', -20))
                     LogModel::Insert('Moderate', 'Discussion', $Data, $LogOptions);
               }
               $Moderate = TRUE;
            }
            if ($Total <= C('Plugins.Voting.ModThreshold2', -20)) {
               // Remove the comment.
               $DiscussionModel->Delete($DiscussionID, array('Log' => 'Moderate'));

               $Sender->InformMessage(sprintf(T('The %s has been removed for moderation.'), T('discussion')));
            } elseif ($Moderate) {
               $Sender->InformMessage(sprintf(T('The %s has been flagged for moderation.'), T('discussion')));
            }
         }
      }
      $Sender->DeliveryType(DELIVERY_TYPE_BOOL);
      $Sender->SetJson('TotalScore', $Total);
      $Sender->SetJson('FinalVote', $FinalVote);
      $Sender->Render();
   }
   /**
    *
    * Coge el campo de puntacion cada vez que las discusiones se consultan.
    * Grab the score field whenever the discussions are queried.
    */
   public function DiscussionModel_AfterDiscussionSummaryQuery_Handler(&$Sender) {
//		if (!C('Plugins.Voting.Enabled'))
//			return;

      $Sender->SQL->Select('d.Score');
   }
    /*
    * Agregar el checkbox "pregunta" cuando se crea la discusión.
    */
   public function PostController_BeforeFormButtons_Handler($Sender) {
      echo $Sender->Form->CheckBox('Pregunta', T('Pregunta'), array('value' => '1')).'</li>';
      echo "<br>";
      echo "<br>";
      }

	/**
	 * cargar el tab "Popular Questions" .
	 */
  public function Base_BeforeDiscussionTabs_Handler($Sender) {
//		if (!C('Plugins.Voting.Enabled'))
//			return;

		echo '<li'.($Sender->RequestMethod == 'popular' ? ' class="Active"' : '').'>'
			.Anchor(T('Popular'), '/discussions/popular', 'PopularDiscussions TabLink')
		.'</li>';
	}

//   public function CategoriesController_BeforeDiscussionContent_Handler($Sender) {
//      $this->DiscussionsController_BeforeDiscussionContent_Handler($Sender);
//   }

   /**
    * Cargar las discusiones populares.
    */
   public function DiscussionsController_Popular_Create($Sender) {
//		if (!C('Plugins.Voting.Enabled'))
//			return;

      $Sender->Title(T('Popular'));
      $Sender->Head->Title($Sender->Head->Title());

      $Offset = GetValue('0', $Sender->RequestArgs, '0');

      // Get rid of announcements from this view
      if ($Sender->Head) {
         $Sender->AddJsFile('discussions.js');
         $Sender->AddJsFile('bookmark.js');
         $Sender->AddJsFile('options.js');
         $Sender->Head->AddRss($Sender->SelfUrl.'/feed.rss', $Sender->Head->Title());
      }
      if (!is_numeric($Offset) || $Offset < 0)
         $Offset = 0;

      // Add Modules
      $Sender->AddModule('NewDiscussionModule');
      $BookmarkedModule = new BookmarkedModule($Sender);
      $BookmarkedModule->GetData();
      $Sender->AddModule($BookmarkedModule);
      $Sender->SetData('Category', FALSE, TRUE);
      $Limit = C('Vanilla.Discussions.PerPage', 30);
      $DiscussionModel = new DiscussionModel();
      $CountDiscussions = $DiscussionModel->GetCount();
      $Sender->SetData('CountDiscussions', $CountDiscussions);
      $Sender->AnnounceData = FALSE;
	  $Sender->SetData('Announcements', array(), TRUE);
      $DiscussionModel->SQL->OrderBy('d.CountViews', 'desc');
      $Sender->DiscussionData = $DiscussionModel->Get($Offset, $Limit);
      $Sender->SetData('Discussions', $Sender->DiscussionData, TRUE);
      $Sender->SetJson('Loading', $Offset . ' to ' . $Limit);

      // Build a pager.
      $PagerFactory = new Gdn_PagerFactory();
      $Sender->Pager = $PagerFactory->GetPager('Pager', $Sender);
      $Sender->Pager->ClientID = 'Pager';
      $Sender->Pager->Configure(
         $Offset,
         $Limit,
         $CountDiscussions,
         'discussions/popular/%1$s'
      );

      // Deliver json data if necessary
      if ($Sender->DeliveryType() != DELIVERY_TYPE_ALL) {
         $Sender->SetJson('LessRow', $Sender->Pager->ToString('less'));
         $Sender->SetJson('MoreRow', $Sender->Pager->ToString('more'));
         $Sender->View = 'discussions';
      }

      // Set a definition of the user's current timezone from the db. jQuery
      // will pick this up, compare to the browser, and update the user's
      // timezone if necessary.
      $CurrentUser = Gdn::Session()->User;
      if (is_object($CurrentUser)) {
         $ClientHour = $CurrentUser->HourOffset + date('G', time());
         $Sender->AddDefinition('SetClientHour', $ClientHour);
      }

      // Render the controller
      $Sender->View = 'index';
      $Sender->Render();
   }
	/**
	 * If turning off scoring, make the forum go back to the traditional "jump
	 * to what I last read" functionality.
	 */
   public function OnDisable() {
		SaveToConfig('Vanilla.Comments.AutoOffset', TRUE);
   }

   /**
   * Don't let the users access the category management screens.
   public function SettingsController_Render_Before(&$Sender) {
      if (strpos(strtolower($Sender->RequestMethod), 'categor') > 0)
         Redirect($Sender->Routes['DefaultPermission']);
   }
   */


	/**
	 * Insert the voting html on comments in a discussion.
	 */
	public function PostController_BeforeCommentMeta_Handler($Sender) {
//		if (!C('Plugins.Voting.Enabled'))
//			return;

		$this->DiscussionController_BeforeCommentMeta_Handler($Sender);
	}

	/**
	 * Add voting css to post controller.
	 */
	public function PostController_Render_Before($Sender) {
      $this->AddJsCss($Sender);
      $this->AddJsmensaje($Sender);
   }

   public function ProfileController_Render_Before($Sender) {
//		if (!C('Plugins.Voting.Enabled'))
//			return;

      $this->AddJsCss($Sender);
   }
   /*
    *Grabar la discusión como pregunta si la opción es la correcta
    */
   public function DiscussionModel_AfterSaveDiscussion_Handler($Sender) {
      $FormPostValues = GetValue('FormPostValues', $Sender->EventArguments, array());
      $DiscussionID = GetValue('DiscussionID', $Sender->EventArguments, array());
      $Pregunta = GetValue('Pregunta', $FormPostValues , array());
      Gdn_Controller::InformMessage(sprintf(T('The s has been removed for moderation.'), T('comment')));
      $UserID = GetValue('UpdateUserID', $FormPostValues , array());
      $SQL=Gdn::SQL();
      $score=$SQL->Select('score')
                 ->From('User')
                 ->Where('UserID',$UserID)
                 ->Get()->Value('score');
      if ($Pregunta =='1'){
          $PuntosporPregunta= GetValue('CostodePregunta', $this->PuntosConfig, array());
          $SQL->Update("Discussion")
            ->Where('DiscussionID',$DiscussionID)
            ->Set('answer',1, FALSE)
            ->Put();
          //quitar puntos cada vez que se haga una pregunta.
          $PuntosFinal=$score-$PuntosporPregunta;
      }
      else{
          $PuntosporPregunta= GetValue('PuntosComentNormal', $this->PuntosConfig, array());
          //Sumar puntos si es un post normal
          $PuntosFinal=$score+$PuntosporPregunta;
      }
       $SQL->Update("User")
        ->Where('UserID',$UserID)
        ->Set('score',$PuntosFinal, FALSE)
        ->Put();
   }

    public function CommentModel_BeforeSaveComment_Handler($Sender) {

      $FormPostValues = GetValue('FormPostValues', $Sender->EventArguments, array());
      $InsertUserID = GetValue('InsertUserID', $FormPostValues, array());
      $DiscussionID= GetValue('DiscussionID', $FormPostValues, array());
      $SQL=Gdn::SQL();
      $Pregunta=$SQL->Select('answer')
                 ->From('Discussion')
                 ->Where('DiscussionID',$DiscussionID)
                 ->Get()->Value('answer');
      $comentariosantes=$SQL->Select('CommentID')
                 ->From('Comment')
                 ->Where('InsertUserID',$InsertUserID)
                 ->Where('DiscussionID',$DiscussionID)
             ->Get()->FirstRow();
      if (!$comentariosantes){
          if($Pregunta==1)$PuntosPorComentario=3;
          else $PuntosPorComentario=1;
          $score=$SQL->Select('score')
                     ->From('User')
                     ->Where('UserID',$InsertUserID)
                     ->Get()->Value('score');
          $PuntosFinal=$score+$PuntosPorComentario;
          $SQL->Update("User")
              ->Where('UserID',$InsertUserID)
              ->Set('score',$PuntosFinal, FALSE)
              ->Put();
      }
}

   /**
    * Al crear un usuario a este se le dan puntos.
    */
   public function UserModel_AfterInsertUser_Handler(&$Sender) {
       $Puntosiniciales= GetValue('Puntosiniciales', $this->PuntosConfig, array());
       $UserID=&$Sender->EventArguments['InsertUserID'];
       $SQL=Gdn::SQL();
       $SQL->Update("User")
           ->Where('UserID',$UserID)
           ->Set('Score',$Puntosiniciales, FALSE)
           ->Put();
   }
   /*
       * Al habilitar a todos los usuarios se le dan puntos.
    */
   public function Setup() {
     //le damos a todos los usuarios al inicializar el plugin 10 puntos.
      $Puntosiniciales= GetValue('Puntosiniciales', $this->PuntosConfig, array());
      $SQL=Gdn::SQL();
       $SQL->Update("User")
        ->Where('Score',NULL)
	    ->Set('Score', $Puntosiniciales, FALSE)
	    ->Put();
        //Crear un campo el la tabla discusion para determinar si es una
      //pregunta (el valore es 1) de lo contrario 0
      $Structure = Gdn::Structure();
      $Structure->Table('Discussion')
         ->Column('answer', 'int',TRUE)
         ->Set(FALSE, FALSE);
      /*
       *Dos bases de datos donde se guarda la puntuacion maxima que puede recibir
       *tener un usuario que halla creado una respuesta.
       */
      $Structure->Table('CommentPuntos')
         ->Column('UserID', 'int',TRUE)
         ->Column('CommentID', 'int',TRUE)
         ->Column('Score', 'int',TRUE)
         ->Set(FALSE, FALSE);
      $Structure->Table('DiscussionPuntos')
         ->Column('UserID', 'int',TRUE)
         ->Column('DiscussionID', 'int',TRUE)
         ->Column('Score', 'int',TRUE)
         ->Set(FALSE, FALSE);
   }

   protected function _Enable() {
      SaveToConfig('Plugins.Votacion.Enabled', TRUE);
   }

   protected function _Disable() {
      RemoveFromConfig('Plugins.Votacion.Enabled');

   }

}

<?php
/**
 * Confirmation by Email Plugin for LimeSurvey
 *
 * @author Thibault Le Meur <t.lemeur@gmail.com>
 * @copyright 2016 Thibault Le Meur <t.lemeur@gmail.com>
 * @license GPL v3
 * @version 1.0
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
class ConfirmByEmail extends PluginBase {
    protected $storage = 'DbStorage';    
    static protected $description = 'Confirm a survey response by email';
    static protected $name = 'Email response';

    protected $settings = array(
        'maxEmails' => array(
        'type' => 'int',
        'default' => 0,
        'label' => 'Maximum number of emails that can be sent by the plugin',
        'help' => 'This sets the maximum number of different email templates that a survey administrator can define for an instance of this plugin.'
        )
    );
    
    public function __construct(PluginManager $manager, $id) 
    {
        parent::__construct($manager, $id);
        
        
        /**
         * Subscribes to plugin settings event for each survey (beforeSurveySettings, newSurveySettings)
         * Subscribes to afterSurveyComplete event in order to send email notification
         */
        $this->subscribe('afterSurveyComplete', 'afterSurveyComplete');
        $this->subscribe('beforeSurveySettings', 'beforeSurveySettings');
        $this->subscribe('newSurveySettings');
    }
   
    /**
     * This event is fired by the administration panel to gather extra settings
     * available for a survey.
     * @param PluginEvent $event
     */
    public function beforeSurveySettings()
    {
        $event      = $this->event;
        $myevent    = $this->getEvent();
        $surveyId   = $event->get('survey');
        $surveyInfo = getSurveyInfo($surveyId);
        $baselang   = $surveyInfo['language'];
        $isHtmlEmail = ($surveyInfo['htmlemail']=='Y');
        $aLangs = array();
        $aLangs[] = $baselang;
        $otherLangs = explode(" ",$surveyInfo['additional_languages']);
        $aLangs = array_merge($aLangs,array_filter($otherLangs));
        $currentEmailCount = $this->get('emailCount', 'Survey', $event->get('survey'));
        $aCountOptions = range(0,$this->get('maxEmails'));

        $mySettings = array();
        $mySettings['emailCount'] = array( 
                    'type' => 'select',
                    'label' => 'Number of different emails to set',
                    'help' => 'When you change this value, the survey settings are automatically saved and different settings will be available for the plugin. Please get back to the plugin settings tab to see the new settings.',
                    'options' => $aCountOptions,
                    'default' => '0',
                    'submitonchange'=> true,
                    'current' => $this->get('emailCount', 'Survey', $event->get('survey'))
                );

        if ($currentEmailCount >= 1) {
            for ($i = 1; $i <= $currentEmailCount; $i++) {
                $mySettings['emailDestinations_'.$i] = array(
                    'type' => 'relevance',
                    'label' => '[email n°'.$i.'] Semi-column separated list of emails to notify',
                    'help' => 'You can use an EM expression to build the list. If the list is empty, no email is sent.',
                    'current' => $this->get('emailDestinations_'.$i, 'Survey', $event->get('survey'))
                );
                $mySettings['emailLang_'.$i] = array( 
                        'type' => 'select',
                        'label' => '[email n°'.$i.'] Language for this email',
                        'options' => array($baselang => 'Survey\'s base language', '--' => 'Response\'s language'),
                        'default' => '--',
                        'current' => $this->get('emailLang_'.$i, 'Survey', $event->get('survey'))
                        );
                $mySettings['emailAttachFiles_'.$i] = array(
                    'type' => 'relevance',
                    'label' => '[email n°'.$i.'] Semi-column separated list of question codes corresponding to FileUpload questions.',
                    'help' => 'The content of each question will be attached to the confirmation email. You can use an EM expression to build the list. If the list is empty, no attachment is sent.',
                    'current' => $this->get('emailAttachFiles_'.$i, 'Survey', $event->get('survey'))
                );
                $lgcount = 0;
                foreach ($aLangs as $lgcode) {
                    $lgcount++;
                    $baseLangNote = "";
                    if ($lgcount == 1) {
                        $baseLangNote = " This is the survey base language template, it will be used by default if no translation is available.";
                    }
                    $mySettings["emailSubject_${i}_${lgcode}"] = array(
                        'type' => 'relevance',
                        'label' => "[email n°$i] (${lgcode}) Subject",
                        'help' => 'You can use an EM expression for micro-tailoring.'.$baseLangNote,
                        'current' => $this->get("emailSubject_${i}_${lgcode}", 'Survey', $event->get('survey'))
                     );
                    $mySettings["emailBody_${i}_${lgcode}"] = array(
                        'type' => 'relevance',
                        'label' => "[email n°$i] (${lgcode}) Content",
                        'help' => 'You can use an EM expression for micro-tailoring. the token {ANSWERCODE} is accepted.'.$baseLangNote,
                        'current' => $this->get("emailBody_${i}_${lgcode}", 'Survey', $event->get('survey'))
                     );
                }
            }
        }
        

        $event->set("surveysettings.{$this->id}", array(
            'name' => get_class($this),
            'settings' => $mySettings
         ));
    }

    /**
     * This event is fired by the administration panel to save extra settings
     * available for a survey.
     * @param PluginEvent $event
     */
    public function newSurveySettings()
    {
        $event = $this->event;
        foreach ($event->get('settings') as $name => $value) {
            
            $this->set($name, $value, 'Survey', $event->get('survey'));
        }
    }
 

    /**
     * This event is fired by when a response is submitted
     * available for a survey.
     * @param PluginEvent $event
     */
    public function afterSurveyComplete() 
    { // This method will send a notification email
        $event      = $this->getEvent();
        $surveyId   = $event->get('surveyId');

        // Only process the afterSurveyComplete if the plugin is Enabled for this survey and if the survey is Active
        if ( $this->get('emailCount','Survey',$surveyId) < 1 
                || Survey::model()->findByPk($surveyId)->active !="Y" ) {
            // leave gracefully
            return true;
        }

        // Retrieve response and survey properties
        $responseId = $event->get('responseId');
        $response   = $this->pluginManager->getAPI()->getResponse($surveyId, $responseId);
        $sitename = $this->pluginManager->getAPI()->getConfigKey('sitename');
        $surveyInfo = getSurveyInfo($surveyId);
        $adminemail = $surveyInfo['adminemail'];
        $bounce_email = $surveyInfo['bounce_email'];
        $isHtmlEmail = ($surveyInfo['htmlemail']=='Y');
        $baseLang = $surveyInfo['language'];

        for ($i = 1; $i <= $this->get('emailCount','Survey',$surveyId); $i++) {
            // Let's check if there is at least a valid destination email address
            $aTo=array();
            $aAttachTo=array();
            $aDestEmail=explode(';',$this->pluginManager->getAPI()->EMevaluateExpression($this->get('emailDestinations_'.$i,'Survey',$surveyId)));
            $aDestEmail = array_map('trim',$aDestEmail);
            $aUploadQuestions=explode(';',$this->pluginManager->getAPI()->EMevaluateExpression($this->get('emailAttachFiles_'.$i,'Survey',$surveyId)));
            $aUploadQuestions = array_map('trim',$aUploadQuestions);
            // prepare an array of valid destination email addresses
            foreach ($aDestEmail as $destemail) {
                if(validateEmailAddress($destemail)) {
                    $aTo[]=$destemail;
                }
            }
            // prepare an array of valid attached files from upload-questions
            foreach ($aUploadQuestions as $uploadQuestion) {
                $sgqa = 0;
                $qtype='';
                if (isset($response[$uploadQuestion])) {
                    // get SGQA code from question-code. Ther might be a better way to do this though...
                    $sgqa  = $this->pluginManager->getAPI()->EMevaluateExpression('{'.$uploadQuestion.'.sgqa}');
                    $qtype  = $this->pluginManager->getAPI()->EMevaluateExpression('{'.$uploadQuestion.'.type}');
                }
                // Only add the file if question is relevant
                if ($sgqa != 0 && $qtype == "|" && \LimeExpressionManager::QuestionIsRelevant($sgqa)) {
                    $aFiles=json_decode($response[$uploadQuestion]);
                    if (!is_null($aFiles) && is_array($aFiles)) {
                        foreach ($aFiles as $file) {   
                            if (property_exists($file,'name') && property_exists($file,'filename')) {
                                $name = $file->name;       
                                $filename = $file->filename;       
                                $aAttachTo[] = Array (
                                        0 => $this->pluginManager->getAPI()->getConfigKey('uploaddir')."/surveys/{$surveyId}/files/".$filename,
                                        1 => $name
                                        );
                            }
                        }
                    }
                }
            }
            if (count($aTo) >= 1) {
                // Retrieve the language to use for the notification email
                $emailLang = $this->get('emailLang_'.$i,'Survey',$surveyId);
                if ($emailLang == '--') { // in this case let's select the language used when submitting the response
                    $emailLang = $response['startlanguage'];
                }
                $subjectTemplate = $this->get("emailSubject_${i}_${emailLang}",'Survey',$surveyId);
                if ($subjectTemplate == "") { // If subject is not translated, use subject and body from the baseLang
                    $emailLang = $baseLang;
                    $subjectTemplate = $this->get("emailSubject_${i}_${emailLang}",'Survey',$surveyId);
                }
                // Process the email subject and body through ExpressionManager
                $subject = $this->pluginManager->getAPI()->EMevaluateExpression($subjectTemplate);
                // Prepare an {ANSWERTABLE} variable
                if ($surveyInfo['datestamp'] == 'N') {
                    //$aFilteredFields=array('id', 'submitdate', 'lastpage', 'startlanguage');
                    // Let's filter submitdate if survey is not datestampped
                    $aFilteredFields = array('submitdate');
                }
                else {
                    //$aFilteredFields=array('id', 'lastpage', 'startlanguage');
                    $aFilteredFields = array();
                }
                $replacementfields = array(
                        'ANSWERTABLE' => $this->translateAnswerTable($surveyId,$responseId,$emailLang,$isHtmlEmail, $aFilteredFields)
                        );
                // Process emailBody through EM and replace {ANSWERTABLE}
                $body = \LimeExpressionManager::ProcessString($this->get("emailBody_${i}_${emailLang}",'Survey',$surveyId), NULL, $replacementfields);


                // At last it's time to send the email
                SendEmailMessage($body, $subject, $aTo, $adminemail, $sitename, $isHtmlEmail, $bounce_email,$aAttachTo);

            } // END BLOCK 'if' aTo[] not emtpy
        } // END BLOCK 'for' emailCount
    }

    /**
    * Returns the answer table of a response to be used
    * as a replacement for {ANSWERTABLE}
    * This is mostly a copy/paste of core code because of lack of factorization.
    * @param int $surveyid : the survey id number
    * @param int $srid : the response id number
    * @param string $lang : the lang code for localization
    * @param boolean $bIsHTML : TRUE if the returned string is HTML formatted
    * @param array $aFilteredFields : array of filtered response fields
    * return string : the replacement string for {ANSWERTABLE}
    **/    
   private function translateAnswerTable ($surveyid, $srid, $lang, $bIsHTML=true, $aFilteredFields=array()) {
	$aFullResponseTable=getFullResponseTable($surveyid,$srid,$lang);
        $ResultTableHTML = "<table class='printouttable' >\n";
        $ResultTableText ="\n\n";
        $oldgid = 0;
        $oldqid = 0;
        foreach ($aFullResponseTable as $sFieldname=>$fname)
        {
            if (substr($sFieldname,0,4)=='gid_')
            {
                $ResultTableHTML .= "\t<tr class='printanswersgroup'><td colspan='2'>".strip_tags($fname[0])."</td></tr>\n";
                $ResultTableText .="\n{$fname[0]}\n\n";
            }
            elseif (substr($sFieldname,0,4)=='qid_')
            {
                $ResultTableHTML .= "\t<tr class='printanswersquestionhead'><td  colspan='2'>".strip_tags($fname[0])."</td></tr>\n";
                $ResultTableText .="\n{$fname[0]}\n";
            }
            elseif (!in_array($sFieldname, $aFilteredFields))
            {
                $ResultTableHTML .= "\t<tr class='printanswersquestion'><td>".strip_tags("{$fname[0]} {$fname[1]}")."</td><td class='printanswersanswertext'>".CHtml::encode($fname[2])."</td></tr>\n";
                $ResultTableText .="     {$fname[0]} {$fname[1]}: {$fname[2]}\n";
            }
        }

        $ResultTableHTML .= "</table>\n";
        $ResultTableText .= "\n\n";
        if ($bIsHTML)
        {
            return $ResultTableHTML;
        }
        else
        {
            return $ResultTableText;
        }
	
   } // END METHOD translateAnswerTable

} // END CLASS ConfirmByEmail

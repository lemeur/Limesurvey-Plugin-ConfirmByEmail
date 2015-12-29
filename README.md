# Limesurvey-Plugin-ConfirmByEmail
This LimeSurvey Plugin is an extended version of the onSubmit notification feature of LimeSurvey

##Main features
This new Email notification plugin will send confirmation emails when a response is submitted.

__Shared features with the standard notification system:__
* several destination emails can be given for a single template
* the templates are localized (a template is proposed for each survey supported language)
* the `{ANSWERTABLE}` variable is recognized

__Main features:__
* multiple email templates for each survey (not limited to the participant's notification, the basic and detailed admin notification from the core feature)
* destination email addresses can be taken from the response (usable for open-surveys with no auto-registration or no allow-save enabled)
* destination email addresses are EM expressions and thus can depend on the participant's answers (this enables email routing based on the response)
* if the destination email addresses list (semi-column separated list) contains no valid email address, no email is sent. This makes it possible to add relevance conditions to confirmation emails. 
* it is possible to attach some files from file-upload questions. As it is setup using an EM expression, conditions are supported to select which file to upload or not.

__Not implemented in this plugin:__
* there is no possibility to attach a static file which would not be uploaded in a file-upload question

##Installation
Simply copy the `ConfirmByEmail` directory and its content to your `plugins/` directory.

##Configuration
__On the plateform__
* First enable the plugin from the LimeSurvey Plugin Manager.
Then set the maximum number of email templates each survey administrator will be able to manage.

__On each survey__
* __Enabling the plugin on the survey__
 * on the survey administration parameters, go to _General Settings_ and open the _Plugins_ tab
 * set the number of emails template you need (the default value of `0` disables the plugin).
  * __Important__: Once updated the Survey Settings form is automatically saved, and you have to get back to the *Plugins* tab to see the new settings.
* __Email destination__: this field is an __EM expression__ that outputs a semi-column separated list of email addresses
 * example 1: you just want to send the email to _foo@bar.org_ and _john@doo.com_, then just type in `foo@bar.com;john@doo.com` 
 * example 2: if you have 2 questions with codes persoEmail and proEmail, then you can type `{proEmail};{persoEmail}`
 * you can of course use complex EM expressions making the resulting list conditionned to answers
  * In the resulting list you can have empty values (which will be ignored): for instance if the EM expression results in `;;;` then no email will be sent
* __Email language__: this field lets you decide if the email will be sent translated with the response's language or with the survey's base language
* __Attachments list__: this field accepts an EM expression that results in a semi-column separated list of question codes. These questions must be of type _File-upload_ and their content will be attached to the email
 * you can of course use complex EM expressions making the resulting list conditionned to answers
 * In the resulting list you can have empty values (which will be ignored): for instance if the EM expression results in `;;;` then no file is attached to the email
* __Email Subject (for each language)__: the subject of the email as an __EM expression__ enabling custom text tailoring
 * If the subject for a specific language is not set, and the email is intended to use this language, then the survey's base-language of both Subject and Content will be used.
* __Email Content (for each language)__: the subject of the email as an __EM expression__ enabling custom text tailoring
 * In the Content you can use the `{ANSWERTABLE}` placeholder as a shortcut. Note that the response _timestamp_ will be hidden if the survey is not set to _DateStamp_
 
 

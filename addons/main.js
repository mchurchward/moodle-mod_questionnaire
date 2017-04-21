// Copyright (C) 2017 Mike Churchward <mike.churchward@poetgroup.org>
//
// Licensed under the Apache License, Version 2.0 (the "License");
// you may not use this file except in compliance with the License.
// You may obtain a copy of the License at
//
// http://www.apache.org/licenses/LICENSE-2.0
//
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.

angular.module('mm.addons.mod_questionnaire', [])

.constant('mmaModQuestionnaireComponent', 'mmaModQuestionnaire')

.config(function($stateProvider) {

    $stateProvider

    .state('site.mod_questionnaire', {
        url: '/mod_questionnaire',
        params: {
            module: null,
            moduleid: null, // Redundant parameter to fix a problem passing object as parameters. To be fixed in MOBILE-1370.
            courseid: null
        },
        views: {
            'site': {
                controller: 'mmaModQuestionnaireIndexCtrl',
                templateUrl: 'addons/mod/questionnaire/templates/index.html'
            }
        }
    });
})

.config(function($mmCourseDelegateProvider, $mmContentLinksDelegateProvider) {
    $mmCourseDelegateProvider.registerContentHandler('mmaModQuestionnaire', 'questionnaire', '$mmaModQuestionnaireHandlers.courseContent');
    $mmContentLinksDelegateProvider.registerLinkHandler('mmaModQuestionnaire', '$mmaModQuestionnaireHandlers.linksHandler');
});

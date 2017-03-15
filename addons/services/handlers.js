// (C) Copyright 2017 Mike Churchward <mike.churchward@poetgroup.org>
//
// Licensed under the Apache License, Version 2.0 (the "License");
// you may not use this file except in compliance with the License.
// You may obtain a copy of the License at
//
//     http://www.apache.org/licenses/LICENSE-2.0
//
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.

angular.module('mm.addons.mod_questionnaire')

/**
 * Mod questionnaire handlers.
 *
 * @module mm.addons.mod_questionnaire
 * @ngdoc service
 * @name $mmaModQuestionnaireHandlers
 */
.factory('$mmaModQuestionnaireHandlers', function($mmCourse, $mmaModQuestionnaire, $state, $mmContentLinksHelper) {
    var self = {};

    /**
     * Course content handler.
     *
     * @module mm.addons.mod_questionnaire
     * @ngdoc method
     * @name $mmaModQuestionnaireHandlers#courseContent
     */
    self.courseContent = function() {

        var self = {};

        /**
         * Whether or not the module is enabled for the site.
         *
         * @return {Boolean}
         */
        self.isEnabled = function() {
            return $mmaModQuestionnaire.isPluginEnabled();
        };

        /**
         * Get the controller.
         *
         * @param {Object} module The module info.
         * @param {Number} courseid The course ID.
         * @return {Function}
         */
        self.getController = function(module, courseid) {
            return function($scope) {
                $scope.title = module.name;
                $scope.icon = $mmCourse.getModuleIconSrc('questionnaire');
                $scope.class = 'mma-mod_questionnaire-handler';
                $scope.action = function(e) {
                    $state.go('site.mod_questionnaire', {module: module, courseid: courseid});
                };
            };
        };

        return self;
    };

    /**
     * Content links handler for module index page.
     *
     * @module mm.addons.mod_questionnaire
     * @ngdoc method
     * @name $mmaModQuestionnaireHandlers#indexLinksHandler
     */
    self.indexLinksHandler = $mmContentLinksHelper.createModuleIndexLinkHandler('mmaModQuestionnaire', 'questionnaire', $mmaModQuestionnaire);

    return self;
});
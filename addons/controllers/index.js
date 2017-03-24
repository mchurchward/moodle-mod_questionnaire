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

angular.module('mm.addons.mod_questionnaire')

/**
 * Questionnaire index controller.
 *
 * @module mm.addons.mod_questionnaire
 * @ngdoc controller
 * @name mmaModQuestionnaireIndexCtrl
 */
.controller('mmaModQuestionnaireIndexCtrl', function($scope, $stateParams, $mmaModQuestionnaire, $mmUtil, $q, $mmCourse) {
    var module = $stateParams.module || {},
        courseId = $stateParams.courseid,
        questionnaire;

    $scope.title = module.name;
    $scope.description = module.description;
    $scope.courseid = courseId;

    $scope.mymessage = "Hello World!";

});

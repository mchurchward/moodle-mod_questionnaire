// (C) Copyright 2015 Martin Dougiamas
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

    // Convenience function to get Questionnaire data.
    function fetchQuestionnaire(refresh) {
        return $mmaModQuestionnaire.getQuestionnaire(courseId, module.id).then(function(questionnaireData) {
            questionnaire = questionnaireData;
            $scope.title = questionnaire.name || $scope.title;
            $scope.description = questionnaire.intro || $scope.description;
            $scope.questionnaire = questionnaire;

            // Requeriments for issue questionnaires not met yet, ommit try to download already issued questionnaires.
            if (questionnaire.requiredtimenotmet) {
                return $q.when();
            }

            // Every time we access we call the issue questionnaire WS, this may fail if the user is not connected so we must retrieve
            // the issued questionnaire to use the cache on failure.
            return $mmaModQuestionnaire.issueQuestionnaire(questionnaire.id).finally(function() {
                return $mmaModQuestionnaire.getIssuedQuestionnaires(questionnaire.id).then(function(issues) {
                    $scope.issues = issues;
                });
            });
        }).catch(function(message) {
            if (!refresh) {
                // Some call failed, retry without using cache since it might be a new activity.
                return refreshAllData();
            }

            if (message) {
                $mmUtil.showErrorModal(message);
            } else {
                $mmUtil.showErrorModal('Error while getting the questionnaire', true);
            }
            return $q.reject();
        });
    }

    // Convenience function to refresh all the data.
    function refreshAllData() {
        var p1 = $mmaModQuestionnaire.invalidateQuestionnaire(courseId),
            questionnaireRequiredTimeNotMet = typeof(questionnaire) != 'undefined' && questionnaire.requiredtimenotmet,
            p2 = questionnaireRequiredTimeNotMet ? $q.when() : $mmaModQuestionnaire.invalidateIssuedQuestionnaires(questionnaire.id);
            p3 = questionnaireRequiredTimeNotMet ? $q.when() : $mmaModQuestionnaire.invalidateDownloadedQuestionnaires(module.id);

        return $q.all([p1, p2, p3]).finally(function() {
            return fetchQuestionnaire(true);
        });
    }

    $scope.openQuestionnaire = function() {

        var modal = $mmUtil.showModalLoading();

        // Extract the first issued, file URLs are always the same.
        var issuedQuestionnaire = $scope.issues[0];

        $mmaModQuestionnaire.openQuestionnaire(issuedQuestionnaire, module.id)
        .catch(function(error) {
            if (error && typeof error == 'string') {
                $mmUtil.showErrorModal(error);
            } else {
                $mmUtil.showErrorModal('Error while downloading the questionnaire', false);
            }
        }).finally(function() {
            modal.dismiss();
        });
    };

    fetchQuestionnaire().then(function() {
        $mmaModQuestionnaire.logView(questionnaire.id).then(function() {
            $mmCourse.checkModuleCompletion(courseId, module.completionstatus);
        });
    }).finally(function() {
        $scope.questionnaireLoaded = true;
    });

    // Pull to refresh.
    $scope.doRefresh = function() {
        refreshAllData().finally(function() {
            $scope.$broadcast('scroll.refreshComplete');
        });
    };

});

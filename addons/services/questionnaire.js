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
 * Questionnaire service.
 *
 * @module mm.addons.mod_questionnaire
 * @ngdoc service
 * @name $mmaModQuestionnaire
 */
.factory('$mmaModQuestionnaire', function($q, $mmSite, $mmFS, $mmUtil, $mmSitesManager, mmaModQuestionnaireComponent, $mmFilepool) {
    var self = {};

    /**
     * Return whether or not the plugin is enabled in a certain site. Plugin is enabled if the questionnaire WS are available.
     *
     * @module mm.addons.mod_questionnaire
     * @ngdoc method
     * @name $mmaModQuestionnaire#isPluginEnabled
     * @param  {String} [siteId] Site ID. If not defined, current site.
     * @return {Promise}         Promise resolved with true if plugin is enabled, rejected or resolved with false otherwise.
     */
    self.isPluginEnabled = function(siteId) {
        siteId = siteId || $mmSite.getId();

        return $mmSitesManager.getSite(siteId).then(function(site) {
            return site.wsAvailable('mod_questionnaire_get_questionnaires_by_courses');
        });
    };

    /**
     * Get a Questionnaire.
     *
     * @module mm.addons.mod_questionnaire
     * @ngdoc method
     * @name $mmaModQuestionnaire#getQuestionnaire
     * @param {Number} courseId Course ID.
     * @param {Number} cmId     Course module ID.
     * @return {Promise}        Promise resolved when the Questionnaire is retrieved.
     */
    self.getQuestionnaire = function(courseId, cmId) {
        var params = {
                courseids: [courseId]
            },
            preSets = {
                cacheKey: getQuestionnaireCacheKey(courseId)
            };

        return $mmSite.read('mod_questionnaire_get_questionnaires_by_courses', params, preSets).then(function(response) {
            if (response.questionnaires) {
                var currentQuestionnaire;
                angular.forEach(response.questionnaires, function(questionnaire) {
                    if (questionnaire.coursemodule == cmId) {
                        currentQuestionnaire = questionnaire;
                    }
                });
                if (currentQuestionnaire) {
                    return currentQuestionnaire;
                }
            }
            return $q.reject();
        });
    };

    /**
     * Get cache key for Questionnaire data WS calls.
     *
     * @param {Number} courseId Course ID.
     * @return {String}         Cache key.
     */
    function getQuestionnaireCacheKey(courseId) {
        return 'mmaModQuestionnaire:questionnaire:' + courseId;
    }

    /**
     * Get cache key for get user responses WS calls.
     *
     * @param {Number} questionnaireId Questionnaire ID.
     * @param {Number} userId User ID.
     * @return {String} Cache key.
     */
    function getUserResponsesCacheKey(questionnaireId, userId) {
        return getUserResponsesCommonCacheKey(questionnaireId) + ':' + userId;
    }

    /**
     * Get common cache key for get user responses WS calls.
     *
     * @param {Number} questionnaireId Questionnaire ID.
     * @return {String} Cache key.
     */
    function getUserResponsesCommonCacheKey(questionnaireId) {
        return 'mmaModQuestionnaire:userResponses:' + questionnaireId;
    }

    /**
     * Get questionnaire responses for a certain user.
     *
     * @module mm.addons.mod_questionnaire
     * @ngdoc method
     * @name $mmaModQuestionnaire#getUserResponses
     * @param {Number} questionnaireId    Questionnaire ID.
     * @param {Number} [userId]           User ID. If not defined use site's current user.
     * @return {Promise}                  Promise resolved with the responses.
     */
    self.getUserResponses = function(questionnaireId, userId) {
        userId = userId || $mmSite.getUserId();

        var params = {
                questionnaireid: questionnaireId,
                userid: userId
            },
            preSets = {
                cacheKey: getUserResponsesCacheKey(questionnaireId, userId)
            };

        return $mmSite.read('mod_questionnaire_get_user_responses', params, preSets).then(function(response) {
            return response.responses;
        });
    };

    return self;
});

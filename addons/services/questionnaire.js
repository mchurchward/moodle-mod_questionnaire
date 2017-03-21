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
 * Questionnaire service.
 *
 * @module mm.addons.mod_questionnaire
 * @ngdoc service
 * @name $mmaModQuestionnaire
 */
.factory('$mmaModQuestionnaire', function($q, $mmSite, $mmFS, $mmUtil, $mmSitesManager, mmaModQuestionnaireComponent, $mmFilepool) {
    var self = {};

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
     * Get issued questionnaires.
     *
     * @module mm.addons.mod_questionnaire
     * @ngdoc method
     * @name $mmaModQuestionnaire#getIssuedQuestionnaires
     * @param {Number} id Questionnaire ID.
     * @return {Promise}  Promise resolved when the issued data is retrieved.
     */
    self.getIssuedQuestionnaires = function(id) {
        var params = {
                questionnaireid: id
            },
            preSets = {
                cacheKey: getIssuedQuestionnairesCacheKey(id)
            };

        return $mmSite.read('mod_questionnaire_get_issued_questionnaires', params, preSets).then(function(response) {
            if (response.issues) {
                return response.issues;
            }
            return $q.reject();
        });
    };

    /**
     * Get cache key for Questionnaire issued data WS calls.
     *
     * @param {Number} id Questionnaire ID.
     * @return {String}   Cache key.
     */
    function getIssuedQuestionnairesCacheKey(id) {
        return 'mmaModQuestionnaire:issued:' + id;
    }

    /**
     * Invalidates Questionnaire data.
     *
     * @module mm.addons.mod_questionnaire
     * @ngdoc method
     * @name $mmaModQuestionnaire#invalidateQuestionnaire
     * @param {Number} courseId Course ID.
     * @return {Promise}        Promise resolved when the data is invalidated.
     */
    self.invalidateQuestionnaire = function(courseId) {
        return $mmSite.invalidateWsCacheForKey(getQuestionnaireCacheKey(courseId));
    };

    /**
     * Invalidates issues questionnaires.
     *
     * @module mm.addons.mod_questionnaire
     * @ngdoc method
     * @name $mmaModQuestionnaire#invalidateIssuedQuestionnaires
     * @param {Number} id Questionnaire ID.
     * @return {Promise}  Promise resolved when the data is invalidated.
     */
    self.invalidateIssuedQuestionnaires = function(id) {
        return $mmSite.invalidateWsCacheForKey(getIssuedQuestionnairesCacheKey(id));
    };

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
     * Report the Questionnaire as being viewed.
     *
     * @module mm.addons.mod_questionnaire
     * @ngdoc method
     * @name $mmaModQuestionnaire#logView
     * @param {String} id Questionnaire ID.
     * @return {Promise}  Promise resolved when the WS call is successful.
     */
    self.logView = function(id) {
        if (id) {
            var params = {
                questionnaireid: id
            };
            return $mmSite.write('mod_questionnaire_view_questionnaire', params);
        }
        return $q.reject();
    };

    /**
     * Issue a questionnaire.
     *
     * @module mm.addons.mod_questionnaire
     * @ngdoc method
     * @name $mmaModQuestionnaire#issueQuestionnaire
     * @param {Number} questionnaireId Questionnaire ID.
     * @return {Promise}  Promise resolved when the WS call is successful.
     */
    self.issueQuestionnaire = function(questionnaireId) {
         var params = {
            questionnaireid: questionnaireId
        };
        return $mmSite.write('mod_questionnaire_issue_questionnaire', params).then(function(response) {
            if (!response || !response.issue) {
                return $q.reject();
            }
        });
    };

    /**
     * Download or open a downloaded questionnaire.
     *
     * @module mm.addons.mod_questionnaire
     * @ngdoc method
     * @name $mmaModQuestionnaire#openQuestionnaire
     * @param {Object} issuedQuestionnaire Issued questionnaire object.
     * @param {Number} moduleId Module id.
     * @return {Promise}  Promise resolved when the WS call is successful.
     */
    self.openQuestionnaire = function(issuedQuestionnaire, moduleId) {

        var siteId = $mmSite.getId(),
            revision = 0,
            timeMod = issuedQuestionnaire.timecreated,
            files = [{fileurl: issuedQuestionnaire.fileurl, filename: issuedQuestionnaire.filename, timemodified: timeMod}];
        if ($mmFS.isAvailable()) {
            // The file system is available.
            promise = $mmFilepool.downloadPackage(siteId, files, mmaModQuestionnaireComponent, moduleId, revision, timeMod).then(function() {
                return $mmFilepool.getUrlByUrl(siteId, issuedQuestionnaire.fileurl, mmaModQuestionnaireComponent, moduleId, timeMod);
            });
        } else {
            // We use the live URL.
            promise = $q.when($mmSite.fixPluginfileURL(issuedQuestionnaire.fileurl));
        }

        return promise.then(function(localUrl) {
            return $mmUtil.openFile(localUrl);
        });
    };

    /**
     * Invalidate downloaded questionnaires.
     *
     * @module mm.addons.mod_questionnaire
     * @ngdoc method
     * @name $mmaModQuestionnaire#invalidateDownloadedQuestionnaires
     * @param {Number} moduleId Module id.
     * @return {Promise}  Promise resolved when the WS call is successful.
     */
    self.invalidateDownloadedQuestionnaires = function(moduleId) {
        return $mmFilepool.invalidateFilesByComponent($mmSite.getId(), mmaModQuestionnaireComponent, moduleId);
    };

    return self;
});

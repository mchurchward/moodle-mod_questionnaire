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
 * Questionnaire service.
 *
 * @module mm.addons.mod_questionnaire
 * @ngdoc service
 * @name $mmaModQuestionnaire
 */
.factory('$mmaModQuestionnaire', function($q, $mmSite, $mmSitesManager, $mmFilepool, mmaModQuestionnaireComponent, $mmUtil) {
    var self = {};

    /**
     * Get cache key for questionnaire data WS calls.
     *
     * @param {Number} courseId Course ID.
     * @return {String}         Cache key.
     */
    function getQuestionnaireDataCacheKey(courseId) {
        return 'mmaModQuestionnaire:questionnaire:' + courseId;
    }

    /**
     * Get cache key for questionnaire access information data WS calls.
     *
     * @param {Number} questionnaireId Questionnaire ID.
     * @return {String}         Cache key.
     */
    function getQuestionnaireAccessInformationDataCacheKey(questionnaireId) {
        return 'mmaModQuestionnaire:access:' + questionnaireId;
    }

    /**
     * Get prefix cache key for questionnaire analysis data WS calls.
     *
     * @param {Number} questionnaireId Questionnaire ID.
     * @return {String}         Cache key.
     */
    function getAnalysisDataPrefixCacheKey(questionnaireId) {
        return 'mmaModQuestionnaire:analysis:' + questionnaireId;
    }

    /**
     * Get cache key for questionnaire analysis data WS calls.
     *
     * @param {Number} questionnaireId Questionnaire ID.
     * @param {Number} [groupId]  Group ID.
     * @return {String}         Cache key.
     */
    function getAnalysisDataCacheKey(questionnaireId, groupId) {
        groupId = groupId || 0;
        return getAnalysisDataPrefixCacheKey(questionnaireId) + ":" + groupId;
    }


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
        return $mmSitesManager.getSite(siteId).then(function(site) {
            return  site.wsAvailable('mod_questionnaire_get_questionnaires_by_courses') &&
                    site.wsAvailable('mod_questionnaire_get_access_information');
        });
    };

    /**
     * Get a questionnaire with key=value. If more than one is found, only the first will be returned.
     *
     * @param  {Number}     courseId        Course ID.
     * @param  {String}     key             Name of the property to check.
     * @param  {Mixed}      value           Value to search.
     * @param  {String}     [siteId]        Site ID. If not defined, current site.
     * @param  {Boolean}    [forceCache]    True to always get the value from cache, false otherwise. Default false.
     * @return {Promise}                    Promise resolved when the questionnaire is retrieved.
     */
    function getQuestionnaire(courseId, key, value, siteId, forceCache) {
        return $mmSitesManager.getSite(siteId).then(function(site) {
            var params = {
                    courseids: [courseId]
                },
                preSets = {
                    cacheKey: getQuestionnaireDataCacheKey(courseId)
                };

            if (forceCache) {
                preSets.omitExpires = true;
            }

            return site.read('mod_questionnaire_get_questionnaires_by_courses', params, preSets).then(function(response) {
                if (response && response.questionnaires) {
                    var current;
                    angular.forEach(response.questionnaires, function(questionnaire) {
                        if (!current && questionnaire[key] == value) {
                            current = questionnaire;
                        }
                    });
                    if (current) {
                        return current;
                    }
                }
                return $q.reject();
            });
        });
    }

    /**
     * Get a questionnaire by course module ID.
     *
     * @module mm.addons.mod_questionnaire
     * @ngdoc method
     * @name $mmaModQuestionnaire#getQuestionnaire
     * @param   {Number}    courseId        Course ID.
     * @param   {Number}    cmId            Course module ID.
     * @param   {String}    [siteId]        Site ID. If not defined, current site.
     * @param   {Boolean}   [forceCache]    True to always get the value from cache, false otherwise. Default false.
     * @return  {Promise}                   Promise resolved when the questionnaire is retrieved.
     */
    self.getQuestionnaire = function(courseId, cmId, siteId, forceCache) {
        return getQuestionnaire(courseId, 'coursemodule', cmId, siteId, forceCache);
    };

    /**
     * Get a questionnaire by ID.
     *
     * @module mm.addons.mod_questionnaire
     * @ngdoc method
     * @name $mmaModQuestionnaire#getQuestionnaireById
     * @param   {Number}    courseId        Course ID.
     * @param   {Number}    id              Questionnaire ID.
     * @param   {String}    [siteId]        Site ID. If not defined, current site.
     * @param   {Boolean}   [forceCache]    True to always get the value from cache, false otherwise. Default false.
     * @return  {Promise}                   Promise resolved when the questionnaire is retrieved.
     */
    self.getQuestionnaireById = function(courseId, id, siteId, forceCache) {
        return getQuestionnaire(courseId, 'id', id, siteId, forceCache);
    };

    /**
     * Invalidates questionnaire data.
     *
     * @module mm.addons.mod_questionnaire
     * @ngdoc method
     * @name $mmaModQuestionnaire#invalidateQuestionnaireData
     * @param {Number} courseId Course ID.
     * @param  {String} [siteId] Site ID. If not defined, current site.
     * @return {Promise}        Promise resolved when the data is invalidated.
     */
    self.invalidateQuestionnaireData = function(courseId, siteId) {
        return $mmSitesManager.getSite(siteId).then(function(site) {
            return site.invalidateWsCacheForKey(getQuestionnaireDataCacheKey(courseId));
        });
    };

    /**
     * Get  access information for a given questionnaire.
     *
     * @module mm.addons.mod_questionnaire
     * @ngdoc method
     * @name $mmaModQuestionnaire#getQuestionnaireAccessInformation
     * @param   {Number}    questionnaireId      Questionnaire ID.
     * @param   {String}    [siteId]        Site ID. If not defined, current site.
     * @return  {Promise}                   Promise resolved when the questionnaire is retrieved.
     */
    self.getQuestionnaireAccessInformation = function(questionnaireId, siteId) {
        return $mmSitesManager.getSite(siteId).then(function(site) {
            var params = {
                    questionnaireid: questionnaireId
                },
                preSets = {
                    cacheKey: getQuestionnaireAccessInformationDataCacheKey(questionnaireId)
                };

            return site.read('mod_questionnaire_get_access_information', params, preSets).then(function(accessData) {
                accessData.capabilities = $mmUtil.objectToKeyValueMap(accessData.capabilities, 'name', 'enabled', 'mod/questionnaire:');
                return accessData;
            });
        });
    };

    /**
     * Invalidates questionnaire access information data.
     *
     * @module mm.addons.mod_questionnaire
     * @ngdoc method
     * @name $mmaModQuestionnaire#invalidateQuestionnaireAccessInformationData
     * @param {Number} questionnaireId   Questionnaire ID.
     * @param  {String} [siteId]    Site ID. If not defined, current site.
     * @return {Promise}        Promise resolved when the data is invalidated.
     */
    self.invalidateQuestionnaireAccessInformationData = function(questionnaireId, siteId) {
        return $mmSitesManager.getSite(siteId).then(function(site) {
            return site.invalidateWsCacheForKey(getQuestionnaireAccessInformationDataCacheKey(questionnaireId));
        });
    };

    /**
     * Get analysis information for a given questionnaire.
     *
     * @module mm.addons.mod_questionnaire
     * @ngdoc method
     * @name $mmaModQuestionnaire#getAnalysis
     * @param   {Number}    questionnaireId      Questionnaire ID.
     * @param   {Number}    [groupId]       Group ID.
     * @param   {String}    [siteId]        Site ID. If not defined, current site.
     * @return  {Promise}                   Promise resolved when the questionnaire is retrieved.
     */
    self.getAnalysis = function(questionnaireId, groupId, siteId) {
        return $mmSitesManager.getSite(siteId).then(function(site) {
            var params = {
                    questionnaireid: questionnaireId
                },
                preSets = {
                    cacheKey: getAnalysisDataCacheKey(questionnaireId, groupId)
                };

            if (groupId) {
                params.groupid = groupId;
            }

            return site.read('mod_questionnaire_get_analysis', params, preSets);
        });
    };

    /**
     * Invalidates questionnaire analysis data.
     *
     * @module mm.addons.mod_questionnaire
     * @ngdoc method
     * @name $mmaModQuestionnaire#invalidateAnalysisData
     * @param {Number} questionnaireId   Questionnaire ID.
     * @param  {String} [siteId]    Site ID. If not defined, current site.
     * @return {Promise}        Promise resolved when the data is invalidated.
     */
    self.invalidateAnalysisData = function(questionnaireId, siteId) {
        return $mmSitesManager.getSite(siteId).then(function(site) {
            return site.invalidateWsCacheForKeyStartingWith(getAnalysisDataPrefixCacheKey(questionnaireId));
        });
    };

    /**
     * Invalidate the prefetched content except files.
     * To invalidate files, use $mmaModQuestionnaire#invalidateFiles.
     *
     * @module mm.addons.mod_questionnaire
     * @ngdoc method
     * @name $mmaModQuestionnaire#invalidateContent
     * @param {Number} moduleId The module ID.
     * @param {Number} courseId Course ID.
     * @param  {String} [siteId] Site ID. If not defined, current site.
     * @return {Promise}
     */
    self.invalidateContent = function(moduleId, courseId, siteId) {
        siteId = siteId || $mmSite.getId();
        return self.getQuestionnaire(courseId, moduleId, siteId, true).then(function(questionnaire) {
            var ps = [];
            // Do not invalidate questionnaire data before getting questionnaire info, we need it!
            ps.push(self.invalidateQuestionnaireData(courseId, siteId));
            ps.push(self.invalidateQuestionnaireAccessInformationData(questionnaire.id, siteId));
            ps.push(self.invalidateAnalysisData(questionnaire.id, siteId));

            return $q.all(ps);
        });
    };

    /**
     * Invalidate the prefetched files.
     *
     * @module mm.addons.mod_questionnaire
     * @ngdoc method
     * @name $mmaModQuestionnaire#invalidateFiles
     * @param {Number} moduleId  The module ID.
     * @param  {String} [siteId] Site ID. If not defined, current site.
     * @return {Promise}         Promise resolved when the files are invalidated.
     */
    self.invalidateFiles = function(moduleId, siteId) {
        return $mmFilepool.invalidateFilesByComponent(siteId, mmaModQuestionnaireComponent, moduleId);
    };

    /**
     * Report the questionnaire as being viewed.
     *
     * @module mm.addons.mod_questionnaire
     * @ngdoc method
     * @name $mmaModQuestionnaire#logView
     * @param {String}  id       Questionnaire ID.
     * @param  {String} [siteId] Site ID. If not defined, current site.
     * @return {Promise}  Promise resolved when the WS call is successful.
     */
    self.logView = function(id, siteId) {
        return $mmSitesManager.getSite(siteId).then(function(site) {
            var params = {
                questionnaireid: id
            };
            return site.write('mod_questionnaire_view_questionnaire', params);
        });
    };

    return self;
});
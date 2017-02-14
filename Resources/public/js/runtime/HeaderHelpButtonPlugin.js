/**
 * Help button will be rendered only if there's at least one help-item available.
 *
 * @since 2.54.0
 *
 * @author Sergei Lissovski <sergei.lissovski@modera.org>
 */
Ext.define('Modera.mjrsecurityintegration.runtime.HeaderHelpButtonPlugin', {
    extend: 'MF.runtime.extensibility.AbstractPlugin',

    requires: [
        'MF.Util',
        'Ext.menu.Menu'
    ],

    /**
     * @private
     * @property {Object[]} helpMenuItems
     */
    /**
     * @private
     * @property {MF.runtime.Workbench} workbench
     */
    /**
     * Optional.
     *
     * @private
     * @property {MF.intent.IntentManager} intentMgr
     */

    /**
     * @param {Object} config
     */
    constructor: function (config) {
        MF.Util.validateRequiredConfigParams(this, config, ['helpMenuItems', 'workbench']);

        Ext.apply(this, config);

        this.callParent(arguments);
    },

    // override
    getId: function() {
        return 'header-help-button'
    },

    // override
    bootstrap: function(callback) {
        var me = this;

        var targetContainer = Ext.ComponentQuery.query('component[extensionPoint=additionalHeaderActions]')[0];
        if (targetContainer && this.helpMenuItems.length > 0 && !this.isButtonAlreadyContributed()) {
            var usernameButtonIndex = 0;
            Ext.each(targetContainer.down('component'), function(cmp, i) {
                if (cmp.hasOwnProperty('itemId') && 'showProfileBtn' == cmp.itemId) {
                    usernameButtonIndex = i;

                    return false;
                }
            });

            var afterUsernamePosition = usernameButtonIndex + 1;

            targetContainer.insert(afterUsernamePosition, {
                itemId: 'helpMenuButton',
                xtype: 'button',
                scale: 'medium',
                margin: '0 10 0 5',
                glyph: FontAwesome.resolve('question-circle'),
                handler: function(btn) {
                    me.showMenu(btn);
                }
            });
        }

        callback();
    },

    // private
    isButtonAlreadyContributed: function() {
        // MPFE-958
        var query = 'component[extensionPoint=additionalHeaderActions] component[itemId=helpMenuButton]';

        return Ext.ComponentQuery.query(query).length > 0;
    },

    // private
    showMenu: function(btn) {
        var me = this;

        var buttons = [];
        Ext.each(this.helpMenuItems, function(item) {
            var btn = Ext.clone(item);
            btn.text = item.label;
            btn.handler = function() {
                me.onButtonPress(btn);
            };

            btn.definition = item;

            buttons.push(btn);
        });

        var menu = Ext.widget({
            xtype: 'menu',
            extensionPoint: 'helpMenuItems',
            items: buttons
        });

        menu.showBy(btn);
    },

    // private
    onButtonPress: function(btn) {
        var me = this;

        var def = btn.definition;

        if (!!def.activityId) {
            me.workbench.launchActivity(def.activityId, def.activityParams || {});
        } else if (!!def.intentId && me.intentMgr) {
            var params = def.intentParams || {};
            params.button = btn;

            me.intentMgr.dispatch({
                name: def.intentId,
                params: params
            });
        } else if (!!def.url) {
            window.open(def.url);
        } else {
            throw Ext.String.format(
                '{0}.onButtonPress(btn): help button with text "{1}" did not declare neither activity, intent nor URL as its action.',
                me.$className, btn.text
            );
        }
    }
});
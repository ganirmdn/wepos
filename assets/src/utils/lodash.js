import lodash from 'lodash/core';
import {
    debounce,
    findIndex,
    forIn,
    includes,
    truncate,
} from 'lodash';

_ = lodash.noConflict();
_.findIndex = findIndex;
_.truncate = truncate;
_.includes = includes;
_.debounce = debounce;
_.forIn = forIn

export default _;

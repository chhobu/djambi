setTimeout(function (one) {
    // only if not supported ...
    if (!one) {
        var
            slice = [].slice,
            // trap original versions
            Timeout = setTimeout,
            Interval = setInterval,
            // create a delegate
            delegate = function (callback, $arguments) {
                $arguments = slice.call($arguments, 2);
                return function () {
                    callback.apply(null, $arguments);
                };
            }
        ;
        // redefine original versions
        setTimeout = function (callback, delay) {
            return Timeout(delegate(callback, arguments), delay);
        };
        setInterval = function (callback, delay) {
            return Interval(delegate(callback, arguments), delay);
        };
    }
}, 0, 1);
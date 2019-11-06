/* mp4upload resolver
 * @lscofield
 * GNU
 */

const cheerio = require('cheerio');
const execPhp = require('exec-php');

exports.index = function (req, res) {
    const source = 'source' in req.body ? req.body.source : req.query.source;
    const html = Buffer.from(source, 'base64').toString('utf8');
    var mp4 = null;

    const $ = cheerio.load(html);

    try {
        var found = '';
        for (var i = 0; i < $('script[type="text/javascript"]').get().length; i++) {
            const text = $('script[type="text/javascript"]').get(i);
            try {
                const s = text.children[0].data;
                if (s.includes("eval(function")) {
                    found = s;
                    break;
                }
            } catch (rt) { }
        }
        if (found != '') {
            execPhp('../lib/unpacker.php', '/usr/bin/php', function (error, php, output) {
                php.nodeunpack(found, function (error, result, output, printed) {
                    if (error) {
                        mp4 = '';
                    } else {
                        var mp4Regex = /player.src\s*\("(([*])*.*?)"/g;
                        var match = mp4Regex.exec(result);
                        mp4 = match[1] != '' ? match[1] : null;
                    }

                    mp4 = mp4 == null ? '' : mp4;

                    res.json({ status: mp4 == '' ? 'error' : 'ok', url: mp4 });
                });
            });
        } else {
            res.json({ status: 'error', url: '' });
        }
    } catch (e) {
        res.json({ status: 'error', url: '' });
    }
};
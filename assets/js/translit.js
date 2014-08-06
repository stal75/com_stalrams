function toTranslit( text, lps ) {
    return text.replace( /([а-яё])|([\s_])|([^a-z\d])/gi,
        function( all, ch, space, words, i ) {
            if ( space || words ) {
                return space ? lps : '';
            }

            var code = ch.charCodeAt(0),
                next = text.charAt( i + 1 ),
                index = code == 1025 || code == 1105 ? 0 :
                    code > 1071 ? code - 1071 : code - 1039,
                t = ['e','a','b','v','g','d','e','zh',
                    'z','i','i','k','l','m','n','o','p',
                    'r','s','t','u','f','kh','ts','ch','sh',
                    'sch','','y','','e','yu','ya'
                ],
                next = next && next.toUpperCase() === next ? 1 : 0;

            return ch.toUpperCase() === ch ? next ? t[ index ].toUpperCase() :
                    t[ index ].substr(0,1).toUpperCase() +
                    t[ index ].substring(1) : t[ index ];
        }
    );
}
function generate() {
	if (trans_form.inputrus.value != '')
	    {trans_form.getbacklog.value = toTranslit(trans_form.inputrus.value, '_');
	     trans_form.getbackpk.value = toTranslit(trans_form.inputrus.value, '-');}
	else { alert('Введите строку!');}
}
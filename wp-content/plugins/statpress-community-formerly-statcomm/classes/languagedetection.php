<?php
/**
 * Created by WpGetReady n @2012
 * Author: Fernando Zorrilla de San Martin
 * Date: 24/05/12
 * Time: 05:51 PM

 * References
 * http://www.thefutureoftheweb.com/blog/use-accept-language-header
 * https://github.com/zendframework/zf2/blob/master/library/Zend/Locale/Locale.php#L582
 * http://techpatterns.com/downloads/scripts/php_language_detection.txt
*/
//echo 'yo, fr-ch;q=0.3, da, en-us;q=0.8, en;q=0.5, fr;q=0.3<p/>';
//var_dump (getLanguages('yo, fr-ch;q=0.3, da, en-us;q=0.8, en;q=0.5, fr;q=0.3, es-uy;q=0.6,zh-cn'));

//var_dump(browserLanguage::getRelevantLanguage('ar-bh;q=0.9,ar-eg'));
//var_dump (browserLanguage::getLanguages('ar,hy,as,az,eu,be,bn,bg,ca,zh-cn,zh-hk,zh-mo,zh-sg,zh-tw'));
//var_dump (browserLanguage::getLanguages('af,sq,ar-dz,ar-bh,ar-eg,ar-iq,ar-jo,ar-kw,ar-lb,ar-ly,ar-ma,ar-om,ar-qa,ar-sa,ar-sy,ar-tn,ar-ae,ar-ye'));


/**
 * Language detection for browser
 * Version 1.0 by FZSM
 * References:
 *
 */
class browserLanguage
{
    private static     $regionTranslation = array(
        'Albania'            => 'AL', 'Algeria'       => 'DZ', 'Argentina'                  => 'AR',
        'Armenia'            => 'AM', 'Australia'     => 'AU', 'Austria'                    => 'AT',
        'Bahrain'            => 'BH', 'Bangladesh'    => 'BD', 'Belgium'                    => 'BE',
        'Belize'             => 'BZ', 'Bhutan'        => 'BT', 'Bolivia'                    => 'BO',
        'Bosnia Herzegovina' => 'BA', 'Brazil'        => 'BR', 'Brazilian'                  => 'BR',
        'Brunei Darussalam'  => 'BN', 'Cameroon'      => 'CM', 'Canada'                     => 'CA',
        'Chile'              => 'CL', 'China'         => 'CN', 'Colombia'                   => 'CO',
        'Costa Rica'         => 'CR', "Cote d'Ivoire" => 'CI', 'Czech Republic'             => 'CZ',
        'Dominican Republic' => 'DO', 'Denmark'       => 'DK', 'Ecuador'                    => 'EC',
        'Egypt'              => 'EG', 'El Salvador'   => 'SV', 'Eritrea'                    => 'ER',
        'Ethiopia'           => 'ET', 'Finland'       => 'FI', 'France'                     => 'FR',
        'Germany'            => 'DE', 'Greece'        => 'GR', 'Guatemala'                  => 'GT',
        'Haiti'              => 'HT', 'Honduras'      => 'HN', 'Hong Kong'                  => 'HK',
        'Hong Kong SAR'      => 'HK', 'Hungary'       => 'HU', 'Iceland'                    => 'IS',
        'India'              => 'IN', 'Indonesia'     => 'ID', 'Iran'                       => 'IR',
        'Iraq'               => 'IQ', 'Ireland'       => 'IE', 'Italy'                      => 'IT',
        'Jamaica'            => 'JM', 'Japan'         => 'JP', 'Jordan'                     => 'JO',
        'Korea'              => 'KR', 'Kuwait'        => 'KW', 'Lebanon'                    => 'LB',
        'Libya'              => 'LY', 'Liechtenstein' => 'LI', 'Luxembourg'                 => 'LU',
        'Macau'              => 'MO', 'Macao SAR'     => 'MO', 'Malaysia'                   => 'MY',
        'Mali'               => 'ML', 'Mexico'        => 'MX', 'Moldava'                    => 'MD',
        'Monaco'             => 'MC', 'Morocco'       => 'MA', 'Netherlands'                => 'NL',
        'New Zealand'        => 'NZ', 'Nicaragua'     => 'NI', 'Nigeria'                    => 'NG',
        'Norway'             => 'NO', 'Oman'          => 'OM', 'Pakistan'                   => 'PK',
        'Panama'             => 'PA', 'Paraguay'      => 'PY', "People's Republic of China" => 'CN',
        'Peru'               => 'PE', 'Philippines'   => 'PH', 'Poland'                     => 'PL',
        'Portugal'           => 'PT', 'PRC'           => 'CN', 'Puerto Rico'                => 'PR',
        'Qatar'              => 'QA', 'Reunion'       => 'RE', 'Russia'                     => 'RU',
        'Saudi Arabia'       => 'SA', 'Senegal'       => 'SN', 'Singapore'                  => 'SG',
        'Slovakia'           => 'SK', 'South Africa'  => 'ZA', 'Spain'                      => 'ES',
        'Sri Lanka'          => 'LK', 'Sweden'        => 'SE', 'Switzerland'                => 'CH',
        'Syria'              => 'SY', 'Taiwan'        => 'TW', 'The Netherlands'            => 'NL',
        'Trinidad'           => 'TT', 'Tunisia'       => 'TN', 'UAE'                        => 'AE',
        'United Kingdom'     => 'GB', 'United States' => 'US', 'Uruguay'                    => 'UY',
        'Venezuela'          => 'VE', 'Yemen'         => 'YE', 'Zimbabwe'                   => 'ZW',
    );

    private static $_localeData = array(
        'root'       => true, 'aa'          => true, 'aa_DJ'       => true, 'aa_ER'      => true, 'aa_ET'       => true,
        'af'         => true, 'af_NA'       => true, 'af_ZA'       => true, 'ak'         => true, 'ak_GH'       => true,
        'am'         => true, 'am_ET'       => true, 'ar'          => true, 'ar_AE'      => true, 'ar_BH'       => true,
        'ar_DZ'      => true, 'ar_EG'       => true, 'ar_IQ'       => true, 'ar_JO'      => true, 'ar_KW'       => true,
        'ar_LB'      => true, 'ar_LY'       => true, 'ar_MA'       => true, 'ar_OM'      => true, 'ar_QA'       => true,
        'ar_SA'      => true, 'ar_SD'       => true, 'ar_SY'       => true, 'ar_TN'      => true, 'ar_YE'       => true,
        'as'         => true, 'as_IN'       => true, 'asa'         => true, 'asa_TZ'     => true, 'az'          => true,
        'az_Arab'    => true, 'az_Arab_IR'  => true, 'az_AZ'       => true, 'az_Cyrl'    => true, 'az_Cyrl_AZ'  => true,
        'az_IR'      => true, 'az_Latn'     => true, 'az_Latn_AZ'  => true, 'be'         => true, 'be_BY'       => true,
        'bem'        => true, 'bem_ZM'      => true, 'bez'         => true, 'bez_TZ'     => true, 'bg'          => true,
        'bg_BG'      => true, 'bm'          => true, 'bm_ML'       => true, 'bn'         => true, 'bn_BD'       => true,
        'bn_IN'      => true, 'bo'          => true, 'bo_CN'       => true, 'bo_IN'      => true, 'br'          => true,
        'br_FR'      => true, 'brx'         => true, 'brx_IN'      => true, 'bs'         => true, 'bs_BA'       => true,
        'byn'        => true, 'byn_ER'      => true, 'ca'          => true, 'ca_ES'      => true, 'cch'         => true,
        'cch_NG'     => true, 'cgg'         => true, 'cgg_UG'      => true, 'chr'        => true, 'chr_US'      => true,
        'cs'         => true, 'cs_CZ'       => true, 'cy'          => true, 'cy_GB'      => true, 'da'          => true,
        'da_DK'      => true, 'dav'         => true, 'dav_KE'      => true, 'de'         => true, 'de_AT'       => true,
        'de_BE'      => true, 'de_CH'       => true, 'de_DE'       => true, 'de_LI'      => true, 'de_LU'       => true,
        'dv'         => true, 'dv_MV'       => true, 'dz'          => true, 'dz_BT'      => true, 'ebu'         => true,
        'ebu_KE'     => true, 'ee'          => true, 'ee_GH'       => true, 'ee_TG'      => true, 'el'          => true,
        'el_CY'      => true, 'el_GR'       => true, 'el_POLYTON'  => true, 'en'         => true, 'en_AS'       => true,
        'en_AU'      => true, 'en_BE'       => true, 'en_BW'       => true, 'en_BZ'      => true, 'en_CA'       => true,
        'en_Dsrt'    => true, 'en_Dsrt_US'  => true, 'en_GB'       => true, 'en_GU'      => true, 'en_HK'       => true,
        'en_IE'      => true, 'en_IN'       => true, 'en_JM'       => true, 'en_MH'      => true, 'en_MP'       => true,
        'en_MT'      => true, 'en_MU'       => true, 'en_NA'       => true, 'en_NZ'      => true, 'en_PH'       => true,
        'en_PK'      => true, 'en_SG'       => true, 'en_Shaw'     => true, 'en_TT'      => true, 'en_UM'       => true,
        'en_US'      => true, 'en_US_POSIX' => true, 'en_VI'       => true, 'en_ZA'      => true, 'en_ZW'       => true,
        'en_ZZ'      => true, 'eo'          => true, 'es'          => true, 'es_419'     => true, 'es_AR'       => true,
        'es_BO'      => true, 'es_CL'       => true, 'es_CO'       => true, 'es_CR'      => true, 'es_DO'       => true,
        'es_EC'      => true, 'es_ES'       => true, 'es_GQ'       => true, 'es_GT'      => true, 'es_HN'       => true,
        'es_MX'      => true, 'es_NI'       => true, 'es_PA'       => true, 'es_PE'      => true, 'es_PR'       => true,
        'es_PY'      => true, 'es_SV'       => true, 'es_US'       => true, 'es_UY'      => true, 'es_VE'       => true,
        'et'         => true, 'et_EE'       => true, 'eu'          => true, 'eu_ES'      => true, 'fa'          => true,
        'fa_AF'      => true, 'fa_IR'       => true, 'ff'          => true, 'ff_SN'      => true, 'fi'          => true,
        'fi_FI'      => true, 'fil'         => true, 'fil_PH'      => true, 'fo'         => true, 'fo_FO'       => true,
        'fr'         => true, 'fr_BE'       => true, 'fr_BF'       => true, 'fr_BI'      => true, 'fr_BJ'       => true,
        'fr_BL'      => true, 'fr_CA'       => true, 'fr_CD'       => true, 'fr_CF'      => true, 'fr_CG'       => true,
        'fr_CH'      => true, 'fr_CI'       => true, 'fr_CM'       => true, 'fr_DJ'      => true, 'fr_FR'       => true,
        'fr_GA'      => true, 'fr_GN'       => true, 'fr_GP'       => true, 'fr_GQ'      => true, 'fr_KM'       => true,
        'fr_LU'      => true, 'fr_MC'       => true, 'fr_MF'       => true, 'fr_MG'      => true, 'fr_ML'       => true,
        'fr_MQ'      => true, 'fr_NE'       => true, 'fr_RE'       => true, 'fr_RW'      => true, 'fr_SN'       => true,
        'fr_TD'      => true, 'fr_TG'       => true, 'fur'         => true, 'fur_IT'     => true, 'ga'          => true,
        'ga_IE'      => true, 'gaa'         => true, 'gaa_GH'      => true, 'gez'        => true, 'gez_ER'      => true,
        'gez_ET'     => true, 'gl'          => true, 'gl_ES'       => true, 'gsw'        => true, 'gsw_CH'      => true,
        'gu'         => true, 'gu_IN'       => true, 'guz'         => true, 'guz_KE'     => true, 'gv'          => true,
        'gv_GB'      => true, 'ha'          => true, 'ha_Arab'     => true, 'ha_Arab_NG' => true, 'ha_Arab_SD'  => true,
        'ha_GH'      => true, 'ha_Latn'     => true, 'ha_Latn_GH'  => true, 'ha_Latn_NE' => true, 'ha_Latn_NG'  => true,
        'ha_NE'      => true, 'ha_NG'       => true, 'ha_SD'       => true, 'haw'        => true, 'haw_US'      => true,
        'he'         => true, 'he_IL'       => true, 'hi'          => true, 'hi_IN'      => true, 'hr'          => true,
        'hr_HR'      => true, 'hu'          => true, 'hu_HU'       => true, 'hy'         => true, 'hy_AM'       => true,
        'ia'         => true, 'id'          => true, 'id_ID'       => true, 'ig'         => true, 'ig_NG'       => true,
        'ii'         => true, 'ii_CN'       => true, 'in'          => true, 'is'         => true, 'is_IS'       => true,
        'it'         => true, 'it_CH'       => true, 'it_IT'       => true, 'iu'         => true, 'iw'          => true,
        'ja'         => true, 'ja_JP'       => true, 'jmc'         => true, 'jmc_TZ'     => true, 'ka'          => true,
        'ka_GE'      => true, 'kab'         => true, 'kab_DZ'      => true, 'kaj'        => true, 'kaj_NG'      => true,
        'kam'        => true, 'kam_KE'      => true, 'kcg'         => true, 'kcg_NG'     => true, 'kde'         => true,
        'kde_TZ'     => true, 'kea'         => true, 'kea_CV'      => true, 'kfo'        => true, 'kfo_CI'      => true,
        'khq'        => true, 'khq_ML'      => true, 'ki'          => true, 'ki_KE'      => true, 'kk'          => true,
        'kk_Cyrl'    => true, 'kk_Cyrl_KZ'  => true, 'kk_KZ'       => true, 'kl'         => true, 'kl_GL'       => true,
        'kln'        => true, 'kln_KE'      => true, 'km'          => true, 'km_KH'      => true, 'kn'          => true,
        'kn_IN'      => true, 'ko'          => true, 'ko_KR'       => true, 'kok'        => true, 'kok_IN'      => true,
        'kpe'        => true, 'kpe_GN'      => true, 'kpe_LR'      => true, 'ksb'        => true, 'ksb_TZ'      => true,
        'ksh'        => true, 'ksh_DE'      => true, 'ku'          => true, 'ku_Arab'    => true, 'ku_Arab_IQ'  => true,
        'ku_Arab_IR' => true, 'ku_IQ'       => true, 'ku_IR'       => true, 'ku_Latn'    => true, 'ku_Latn_SY'  => true,
        'ku_Latn_TR' => true, 'ku_SY'       => true, 'ku_TR'       => true, 'kw'         => true, 'kw_GB'       => true,
        'ky'         => true, 'ky_KG'       => true, 'lag'         => true, 'lag_TZ'     => true, 'lg'          => true,
        'lg_UG'      => true, 'ln'          => true, 'ln_CD'       => true, 'ln_CG'      => true, 'lo'          => true,
        'lo_LA'      => true, 'lt'          => true, 'lt_LT'       => true, 'luo'        => true, 'luo_KE'      => true,
        'luy'        => true, 'luy_KE'      => true, 'lv'          => true, 'lv_LV'      => true, 'mas'         => true,
        'mas_KE'     => true, 'mas_TZ'      => true, 'mer'         => true, 'mer_KE'     => true, 'mfe'         => true,
        'mfe_MU'     => true, 'mg'          => true, 'mg_MG'       => true, 'mi'         => true, 'mi_NZ'       => true,
        'mk'         => true, 'mk_MK'       => true, 'ml'          => true, 'ml_IN'      => true, 'mn'          => true,
        'mn_CN'      => true, 'mn_Cyrl'     => true, 'mn_Cyrl_MN'  => true, 'mn_MN'      => true, 'mn_Mong'     => true,
        'mn_Mong_CN' => true, 'mo'          => true, 'mr'          => true, 'mr_IN'      => true, 'ms'          => true,
        'ms_BN'      => true, 'ms_MY'       => true, 'mt'          => true, 'mt_MT'      => true, 'my'          => true,
        'my_MM'      => true, 'naq'         => true, 'naq_NA'      => true, 'nb'         => true, 'nb_NO'       => true,
        'nd'         => true, 'nd_ZW'       => true, 'nds'         => true, 'nds_DE'     => true, 'ne'          => true,
        'ne_IN'      => true, 'ne_NP'       => true, 'nl'          => true, 'nl_BE'      => true, 'nl_NL'       => true,
        'nn'         => true, 'nn_NO'       => true, 'no'          => true, 'nr'         => true, 'nr_ZA'       => true,
        'nso'        => true, 'nso_ZA'      => true, 'ny'          => true, 'ny_MW'      => true, 'nyn'         => true,
        'nyn_UG'     => true, 'oc'          => true, 'oc_FR'       => true, 'om'         => true, 'om_ET'       => true,
        'om_KE'      => true, 'or'          => true, 'or_IN'       => true, 'pa'         => true, 'pa_Arab'     => true,
        'pa_Arab_PK' => true, 'pa_Guru'     => true, 'pa_Guru_IN'  => true, 'pa_IN'      => true, 'pa_PK'       => true,
        'pl'         => true, 'pl_PL'       => true, 'ps'          => true, 'ps_AF'      => true, 'pt'          => true,
        'pt_AO'      => true, 'pt_BR'       => true, 'pt_GW'       => true, 'pt_MZ'      => true, 'pt_PT'       => true,
        'rm'         => true, 'rm_CH'       => true, 'ro'          => true, 'ro_MD'      => true, 'ro_RO'       => true,
        'rof'        => true, 'rof_TZ'      => true, 'ru'          => true, 'ru_MD'      => true, 'ru_RU'       => true,
        'ru_UA'      => true, 'rw'          => true, 'rw_RW'       => true, 'rwk'        => true, 'rwk_TZ'      => true,
        'sa'         => true, 'sa_IN'       => true, 'saq'         => true, 'saq_KE'     => true, 'se'          => true,
        'se_FI'      => true, 'se_NO'       => true, 'seh'         => true, 'seh_MZ'     => true, 'ses'         => true,
        'ses_ML'     => true, 'sg'          => true, 'sg_CF'       => true, 'sh'         => true, 'sh_BA'       => true,
        'sh_CS'      => true, 'sh_YU'       => true, 'shi'         => true, 'shi_Latn'   => true, 'shi_Latn_MA' => true,
        'shi_MA'     => true, 'shi_Tfng'    => true, 'shi_Tfng_MA' => true, 'si'         => true, 'si_LK'       => true,
        'sid'        => true, 'sid_ET'      => true, 'sk'          => true, 'sk_SK'      => true, 'sl'          => true,
        'sl_SI'      => true, 'sn'          => true, 'sn_ZW'       => true, 'so'         => true, 'so_DJ'       => true,
        'so_ET'      => true, 'so_KE'       => true, 'so_SO'       => true, 'sq'         => true, 'sq_AL'       => true,
        'sr'         => true, 'sr_BA'       => true, 'sr_CS'       => true, 'sr_Cyrl'    => true, 'sr_Cyrl_BA'  => true,
        'sr_Cyrl_CS' => true, 'sr_Cyrl_ME'  => true, 'sr_Cyrl_RS'  => true, 'sr_Cyrl_YU' => true, 'sr_Latn'     => true,
        'sr_Latn_BA' => true, 'sr_Latn_CS'  => true, 'sr_Latn_ME'  => true, 'sr_Latn_RS' => true, 'sr_Latn_YU'  => true,
        'sr_ME'      => true, 'sr_RS'       => true, 'sr_YU'       => true, 'ss'         => true, 'ss_SZ'       => true,
        'ss_ZA'      => true, 'ssy'         => true, 'ssy_ER'      => true, 'st'         => true, 'st_LS'       => true,
        'st_ZA'      => true, 'sv'          => true, 'sv_FI'       => true, 'sv_SE'      => true, 'sw'          => true,
        'sw_KE'      => true, 'sw_TZ'       => true, 'syr'         => true, 'syr_SY'     => true, 'ta'          => true,
        'ta_IN'      => true, 'ta_LK'       => true, 'te'          => true, 'te_IN'      => true, 'teo'         => true,
        'teo_KE'     => true, 'teo_UG'      => true, 'tg'          => true, 'tg_Cyrl'    => true, 'tg_Cyrl_TJ'  => true,
        'tg_TJ'      => true, 'th'          => true, 'th_TH'       => true, 'ti'         => true, 'ti_ER'       => true,
        'ti_ET'      => true, 'tig'         => true, 'tig_ER'      => true, 'tl'         => true, 'tl_PH'       => true,
        'tn'         => true, 'tn_ZA'       => true, 'to'          => true, 'to_TO'      => true, 'tr'          => true,
        'tr_TR'      => true, 'trv'         => true, 'trv_TW'      => true, 'ts'         => true, 'ts_ZA'       => true,
        'tt'         => true, 'tt_RU'       => true, 'tzm'         => true, 'tzm_Latn'   => true, 'tzm_Latn_MA' => true,
        'tzm_MA'     => true, 'ug'          => true, 'ug_Arab'     => true, 'ug_Arab_CN' => true, 'ug_CN'       => true,
        'uk'         => true, 'uk_UA'       => true, 'ur'          => true, 'ur_IN'      => true, 'ur_PK'       => true,
        'uz'         => true, 'uz_Arab'     => true, 'uz_Arab_AF'  => true, 'uz_AF'      => true, 'uz_Cyrl'     => true,
        'uz_Cyrl_UZ' => true, 'uz_Latn'     => true, 'uz_Latn_UZ'  => true, 'uz_UZ'      => true, 've'          => true,
        've_ZA'      => true, 'vi'          => true, 'vi_VN'       => true, 'vun'        => true, 'vun_TZ'      => true,
        'wal'        => true, 'wal_ET'      => true, 'wo'          => true, 'wo_Latn'    => true, 'wo_Latn_SN'  => true,
        'wo_SN'      => true, 'xh'          => true, 'xh_ZA'       => true, 'xog'        => true, 'xog_UG'      => true,
        'yo'         => true, 'yo_NG'       => true, 'zh'          => true, 'zh_CN'      => true, 'zh_Hans'     => true,
        'zh_Hans_CN' => true, 'zh_Hans_HK'  => true, 'zh_Hans_MO'  => true, 'zh_Hans_SG' => true, 'zh_Hant'     => true,
        'zh_Hant_HK' => true, 'zh_Hant_MO'  => true, 'zh_Hant_TW'  => true, 'zh_HK'      => true, 'zh_MO'       => true,
        'zh_SG'      => true, 'zh_TW'       => true, 'zu'          => true, 'zu_ZA'      => true
    );

    private static  $_languageTranslation = array(
        'Afrikaans'         => 'af',      'Albanian'         => 'sq',      'Amharic'          => 'am',
        'Arabic'            => 'ar',      'Armenian'         => 'hy',      'Assamese'         => 'as',
        'Azeri'             => 'az',      'Azeri Latin'      => 'az_Latn', 'Azeri Cyrillic'   => 'az_Cyrl',
        'Basque'            => 'eu',      'Belarusian'       => 'be',      'Bengali'          => 'bn',
        'Bengali Latin'     => 'bn_Latn', 'Bosnian'          => 'bs',      'Bulgarian'        => 'bg',
        'Burmese'           => 'my',      'Catalan'          => 'ca',      'Cherokee'         => 'chr',
        'Chinese'           => 'zh',      'Croatian'         => 'hr',      'Czech'            => 'cs',
        'Danish'            => 'da',      'Divehi'           => 'dv',      'Dutch'            => 'nl',
        'English'           => 'en',      'Estonian'         => 'et',      'Faroese'          => 'fo',
        'Faeroese'          => 'fo',      'Farsi'            => 'fa',      'Filipino'         => 'fil',
        'Finnish'           => 'fi',      'French'           => 'fr',      'Frisian'          => 'fy',
        'Macedonian'        => 'mk',      'Gaelic'           => 'gd',      'Galician'         => 'gl',
        'Georgian'          => 'ka',      'German'           => 'de',      'Greek'            => 'el',
        'Guarani'           => 'gn',      'Gujarati'         => 'gu',      'Hausa'            => 'ha',
        'Hawaiian'          => 'haw',     'Hebrew'           => 'he',      'Hindi'            => 'hi',
        'Hungarian'         => 'hu',      'Icelandic'        => 'is',      'Igbo'             => 'ig',
        'Indonesian'        => 'id',      'Inuktitut'        => 'iu',      'Italian'          => 'it',
        'Japanese'          => 'ja',      'Kannada'          => 'kn',      'Kanuri'           => 'kr',
        'Kashmiri'          => 'ks',      'Kazakh'           => 'kk',      'Khmer'            => 'km',
        'Konkani'           => 'kok',     'Korean'           => 'ko',      'Kyrgyz'           => 'ky',
        'Lao'               => 'lo',      'Latin'            => 'la',      'Latvian'          => 'lv',
        'Lithuanian'        => 'lt',      'Macedonian'       => 'mk',      'Malay'            => 'ms',
        'Malayalam'         => 'ml',      'Maltese'          => 'mt',      'Manipuri'         => 'mni',
        'Maori'             => 'mi',      'Marathi'          => 'mr',      'Mongolian'        => 'mn',
        'Nepali'            => 'ne',      'Norwegian'        => 'no',      'Norwegian Bokmal' => 'nb',
        'Norwegian Nynorsk' => 'nn',      'Oriya'            => 'or',      'Oromo'            => 'om',
        'Papiamentu'        => 'pap',     'Pashto'           => 'ps',      'Polish'           => 'pl',
        'Portuguese'        => 'pt',      'Punjabi'          => 'pa',      'Quecha'           => 'qu',
        'Quechua'           => 'qu',      'Rhaeto-Romanic'   => 'rm',      'Romanian'         => 'ro',
        'Russian'           => 'ru',      'Sami'             => 'smi',     'Sami Inari'       => 'smn',
        'Sami Lule'         => 'smj',     'Sami Northern'    => 'se',      'Sami Skolt'       => 'sms',
        'Sami Southern'     => 'sma',     'Sanskrit'         => 'sa',      'Serbian'          => 'sr',
        'Serbian Latin'     => 'sr_Latn', 'Serbian Cyrillic' => 'sr_Cyrl', 'Sindhi'           => 'sd',
        'Sinhalese'         => 'si',      'Slovak'           => 'sk',      'Slovenian'        => 'sl',
        'Somali'            => 'so',      'Sorbian'          => 'wen',     'Spanish'          => 'es',
        'Swahili'           => 'sw',      'Swedish'          => 'sv',      'Syriac'           => 'syr',
        'Tajik'             => 'tg',      'Tamazight'        => 'tmh',     'Tamil'            => 'ta',
        'Tatar'             => 'tt',      'Telugu'           => 'te',      'Thai'             => 'th',
        'Tibetan'           => 'bo',      'Tigrigna'         => 'ti',      'Tsonga'           => 'ts',
        'Tswana'            => 'tn',      'Turkish'          => 'tr',      'Turkmen'          => 'tk',
        'Uighur'            => 'ug',      'Ukrainian'        => 'uk',      'Urdu'             => 'ur',
        'Uzbek'             => 'uz',      'Uzbek Latin'      => 'uz_Latn', 'Uzbek Cyrillic'   => 'uz_Cyrl',
        'Venda'             => 've',      'Vietnamese'       => 'vi',      'Welsh'            => 'cy',
        'Xhosa'             => 'xh',      'Yiddish'          => 'yi',      'Yoruba'           => 'yo',
        'Zulu'              => 'zu',
    );

    /**
     *
     * @static
     * @param $acceptLanguage: HTTP_ACCEPT_LANGUAGE server variable to analyze
     * @return array ordered by relevance descendant.
     * [0]: relevance from 1=relevant to 0=no relevant
     * [1]: String in the format language-country (usually 2chars-2chars) or only language (2 chars) ej: 'zh-sg' or 'bg'
     * [2]: language name (in English)
     * [3]: Country (if exists)
     * [4]: true if combination language-contry is valid, false if invalid, empty if does not apply (when no country)
     * [5]: Composed 'Language (Country)' text or only 'Language'. ex 'Chinese (Singapore)'
     */
    static function getLanguages($acceptLanguage)
    {
        //$langs = array();
        $lc=array();

        if (isset($acceptLanguage)) {
            // break up string into pieces (languages and q factors)
            preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', $acceptLanguage, $lang_parse);

            if (count($lang_parse[1])) {
                // create a list like "en" => 0.8
                $langs = array_combine($lang_parse[1], $lang_parse[4]);
                foreach ($langs as $lang => $val) {
                    $country="";
                    $language="";
                    $validCombo=''; //decides if the combination language-country is valid
                    $territoryCode='';
                    // set default to 1 for any without q factor

                    if ($val === '')
                    {
                        $langs[$lang] = 1;
                        $val=1;
                    }

                    $langCountry=explode('-',$lang); //first part language, second (if exists), country
                    switch (count($langCountry))
                    {
                        case 1 : //only language
                            //get the language from $_languageTranslation
                            $language=array_search($langCountry[0],self::$_languageTranslation);
                            $language=($language===FALSE)?"":$language;
                            $territoryCode=$language;
                            break;
                        case 2: //language and country
                            $language=array_search($langCountry[0],self::$_languageTranslation);
                            $language=($language===FALSE)?"":$language;
                            $country=array_search(strtoupper($langCountry[1]),self::$regionTranslation);
                            $country=($country===FALSE)?"":$country;
                            $code=strtolower($langCountry[0]) . "_" . strtoupper($langCountry[0]);
                            $validCombo=array_search($code,self::$_localeData);
                            $validCombo=($validCombo===FALSE)?false:true;
                            /* We didn't find it very useful so we took it out
                             $territoryCode=array_search($code,$_territoryData);
                             $territoryCode=($territoryCode===FALSE)?"":$territoryCode;
                            */
                            $territoryCode = "$language ($country)";
                            break;
                        default: //error
                    }
                    $lc[]=array ($val,$lang,$language, $country,$validCombo,$territoryCode);
                }
                // sort list based on value
                usort ($lc,"browserLanguage::compare");
            }
        }
        return $lc;
    }

    /*
     * Auxiliar function used to order array in descent form
     */
    static function compare($a,$b)
    {
        return ($a[0]<=$b[0]);
    }


    /**
     * Return the most relevant language, if there are many with same relevance, return the first one. Null
     * if no elements found.
     * @static
     * @param $acceptLanguage
     * @return array|null
     */
    static function getRelevantLanguage($acceptLanguage)
    {
        $languages=self::getLanguages($acceptLanguage);
        if (count($languages)>0)
            //return first one
            return array($languages[0][1],$languages[0][5]);
        else
            return '';
    }

}
/*
   private static $_territoryData = array(
            'AC' => 'fr_CA',      'AD' => 'ca_AD',      'AE' => 'ar_AE',      'AF' => 'fa_AF',      'AG' => 'en_AG',
            'AI' => 'en_AI',      'AL' => 'sq_AL',      'AM' => 'hy_AM',      'AN' => 'pap_AN',     'AO' => 'pt_AO',
            'AQ' => 'und_AQ',     'AR' => 'es_AR',      'AS' => 'sm_AS',      'AT' => 'de_AT',      'AU' => 'en_AU',
            'AW' => 'nl_AW',      'AX' => 'sv_AX',      'AZ' => 'az_Latn_AZ', 'BA' => 'bs_BA',      'BB' => 'en_BB',
            'BD' => 'bn_BD',      'BE' => 'nl_BE',      'BF' => 'mos_BF',     'BG' => 'bg_BG',      'BH' => 'ar_BH',
            'BI' => 'rn_BI',      'BJ' => 'fr_BJ',      'BL' => 'fr_BL',      'BM' => 'en_BM',      'BN' => 'ms_BN',
            'BO' => 'es_BO',      'BR' => 'pt_BR',      'BS' => 'en_BS',      'BT' => 'dz_BT',      'BV' => 'und_BV',
            'BW' => 'en_BW',      'BY' => 'be_BY',      'BZ' => 'en_BZ',      'CA' => 'en_CA',      'CC' => 'ms_CC',
            'CD' => 'sw_CD',      'CF' => 'fr_CF',      'CG' => 'fr_CG',      'CH' => 'de_CH',      'CI' => 'fr_CI',
            'CK' => 'en_CK',      'CL' => 'es_CL',      'CM' => 'fr_CM',      'CN' => 'zh_Hans_CN', 'CO' => 'es_CO',
            'CP' => 'fr_CP',      'CR' => 'es_CR',      'CU' => 'es_CU',      'CV' => 'kea_CV',     'CX' => 'en_CX',
            'CY' => 'el_CY',      'CZ' => 'cs_CZ',      'DE' => 'de_DE',      'DG' => 'en_DG',      'DJ' => 'aa_DJ',
            'DK' => 'da_DK',      'DM' => 'en_DM',      'DO' => 'es_DO',      'DZ' => 'ar_DZ',      'EA' => 'es_EA',
            'EC' => 'es_EC',      'EE' => 'et_EE',      'EG' => 'ar_EG',      'EH' => 'ar_EH',      'ER' => 'ti_ER',
            'ES' => 'es_ES',      'ET' => 'en_ET',      'FI' => 'fi_FI',      'FJ' => 'en_FJ',      'FK' => 'en_FK',
            'FM' => 'chk_FM',     'FO' => 'fo_FO',      'FR' => 'fr_FR',      'GA' => 'fr_GA',      'GB' => 'en_GB',
            'GD' => 'en_GD',      'GE' => 'ka_GE',      'GF' => 'fr_GF',      'GG' => 'en_GG',      'GH' => 'ak_GH',
            'GI' => 'en_GI',      'GL' => 'iu_GL',      'GM' => 'en_GM',      'GN' => 'fr_GN',      'GP' => 'fr_GP',
            'GQ' => 'es_GQ',      'GR' => 'el_GR',      'GS' => 'und_GS',     'GT' => 'es_GT',      'GU' => 'en_GU',
            'GW' => 'pt_GW',      'GY' => 'en_GY',      'HK' => 'zh_Hant_HK', 'HM' => 'und_HM',     'HN' => 'es_HN',
            'HR' => 'hr_HR',      'HT' => 'ht_HT',      'HU' => 'hu_HU',      'IC' => 'es_IC',      'ID' => 'id_ID',
            'IE' => 'en_IE',      'IL' => 'he_IL',      'IM' => 'en_IM',      'IN' => 'hi_IN',      'IO' => 'und_IO',
            'IQ' => 'ar_IQ',      'IR' => 'fa_IR',      'IS' => 'is_IS',      'IT' => 'it_IT',      'JE' => 'en_JE',
            'JM' => 'en_JM',      'JO' => 'ar_JO',      'JP' => 'ja_JP',      'KE' => 'en_KE',      'KG' => 'ky_Cyrl_KG',
            'KH' => 'km_KH',      'KI' => 'en_KI',      'KM' => 'ar_KM',      'KN' => 'en_KN',      'KP' => 'ko_KP',
            'KR' => 'ko_KR',      'KW' => 'ar_KW',      'KY' => 'en_KY',      'KZ' => 'ru_KZ',      'LA' => 'lo_LA',
            'LB' => 'ar_LB',      'LC' => 'en_LC',      'LI' => 'de_LI',      'LK' => 'si_LK',      'LR' => 'en_LR',
            'LS' => 'st_LS',      'LT' => 'lt_LT',      'LU' => 'fr_LU',      'LV' => 'lv_LV',      'LY' => 'ar_LY',
            'MA' => 'ar_MA',      'MC' => 'fr_MC',      'MD' => 'ro_MD',      'ME' => 'sr_Latn_ME', 'MF' => 'fr_MF',
            'MG' => 'mg_MG',      'MH' => 'en_MH',      'MK' => 'mk_MK',      'ML' => 'bm_ML',      'MM' => 'my_MM',
            'MN' => 'mn_Cyrl_MN', 'MO' => 'zh_Hant_MO', 'MP' => 'en_MP',      'MQ' => 'fr_MQ',      'MR' => 'ar_MR',
            'MS' => 'en_MS',      'MT' => 'mt_MT',      'MU' => 'mfe_MU',     'MV' => 'dv_MV',      'MW' => 'en_MW',
            'MX' => 'es_MX',      'MY' => 'ms_MY',      'MZ' => 'pt_MZ',      'NA' => 'kj_NA',      'NC' => 'fr_NC',
            'NE' => 'ha_Latn_NE', 'NF' => 'en_NF',      'NG' => 'en_NG',      'NI' => 'es_NI',      'NL' => 'nl_NL',
            'NO' => 'nb_NO',      'NP' => 'ne_NP',      'NR' => 'en_NR',      'NU' => 'en_NU',      'NZ' => 'en_NZ',
            'OM' => 'ar_OM',      'PA' => 'es_PA',      'PE' => 'es_PE',      'PF' => 'fr_PF',      'PG' => 'tpi_PG',
            'PH' => 'tl_PH',      'PK' => 'ur_PK',      'PL' => 'pl_PL',      'PM' => 'fr_PM',      'PN' => 'en_PN',
            'PR' => 'es_PR',      'PS' => 'ar_PS',      'PT' => 'pt_PT',      'PW' => 'pau_PW',     'PY' => 'gn_PY',
            'QA' => 'ar_QA',      'RE' => 'fr_RE',      'RO' => 'ro_RO',      'RS' => 'sr_Cyrl_RS', 'RU' => 'ru_RU',
            'RW' => 'rw_RW',      'SA' => 'ar_SA',      'SB' => 'en_SB',      'SC' => 'crs_SC',     'SD' => 'ar_SD',
            'SE' => 'sv_SE',      'SG' => 'en_SG',      'SH' => 'en_SH',      'SI' => 'sl_SI',      'SJ' => 'nb_SJ',
            'SK' => 'sk_SK',      'SL' => 'kri_SL',     'SM' => 'it_SM',      'SN' => 'fr_SN',      'SO' => 'so_SO',
            'SR' => 'nl_SR',      'ST' => 'pt_ST',      'SV' => 'es_SV',      'SY' => 'ar_SY',      'SZ' => 'en_SZ',
            'TA' => 'en_TA',      'TC' => 'en_TC',      'TD' => 'fr_TD',      'TF' => 'fr_TF',      'TG' => 'fr_TG',
            'TH' => 'th_TH',      'TJ' => 'tg_Cyrl_TJ', 'TK' => 'tkl_TK',     'TL' => 'pt_TL',      'TM' => 'tk_TM',
            'TN' => 'ar_TN',      'TO' => 'to_TO',      'TR' => 'tr_TR',      'TT' => 'en_TT',      'TV' => 'tvl_TV',
            'TW' => 'zh_Hant_TW', 'TZ' => 'sw_TZ',      'UA' => 'uk_UA',      'UG' => 'sw_UG',      'UM' => 'en_UM',
            'US' => 'en_US',      'UY' => 'es_UY',      'UZ' => 'uz_Cyrl_UZ', 'VA' => 'it_VA',      'VC' => 'en_VC',
            'VE' => 'es_VE',      'VG' => 'en_VG',      'VI' => 'en_VI',      'VN' => 'vi_VN',      'VU' => 'bi_VU',
            'WF' => 'wls_WF',     'WS' => 'sm_WS',      'YE' => 'ar_YE',      'YT' => 'swb_YT',     'ZA' => 'en_ZA',
            'ZM' => 'en_ZM',      'ZW' => 'sn_ZW'
        );

 */
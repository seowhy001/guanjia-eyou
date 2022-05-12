SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for #@__weapp_seowhy
-- ----------------------------
DROP TABLE IF EXISTS `#@__weapp_seowhy`;
CREATE TABLE `#@__weapp_seowhy` (
                                    `id` int(11) NOT NULL AUTO_INCREMENT,
                                    `key` varchar(20) DEFAULT '0' COMMENT '标识',
                                    `content` varchar(10000) DEFAULT '' COMMENT '内容',
                                    PRIMARY KEY (`id`),
                                    UNIQUE KEY `idx_key` (`key`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

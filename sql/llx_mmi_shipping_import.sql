
CREATE TABLE `llx_mmi_shipping_import` (
  `rowid` int(11) NOT NULL,
  `datec` timestamp NOT NULL DEFAULT current_timestamp(),
  `tms` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `fk_c_shipping_carrier` int(11) NOT NULL,
  `lines_nb` int(11) NOT NULL,
  `date_begin` date NOT NULL,
  `date_end` date NOT NULL,
  `lines_ok` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `llx_mmi_shipping_import`
  ADD PRIMARY KEY (`rowid`),
  ADD KEY `fk_carrier` (`fk_c_shipping_carrier`);

ALTER TABLE `llx_mmi_shipping_import`
  MODIFY `rowid` int(11) NOT NULL AUTO_INCREMENT;
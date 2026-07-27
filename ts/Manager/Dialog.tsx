import React from 'react';
import { createPortal } from 'react-dom';

type Props = {
    open: boolean;
    onClose?: () => void;
	title: string;
	children: React.ReactNode;
}

const Dialog = ({
	open,
	title,
	children,
	onClose = () => undefined,
}: Props): JSX.Element => {

	if (!open) {
		return <></>;
	}

	// Render into <body> via a portal so the dialog escapes the surrounding
	// table row. A `position: fixed` element anchors to the viewport only when
	// no ancestor establishes a containing block (transform/filter/contain — any
	// of which NC34's layout may add). Portalling to body guarantees viewport
	// centering regardless of the mount point in the component tree.
	return createPortal(
		<>
			<div className="oc-dialog-dim bbb-dialog-dim" onClick={() => onClose()}> </div>
			<div className="oc-dialog bbb-dialog" tabIndex={-1} role="dialog">
				<h2 className="oc-dialog-title">{title}</h2>
				<a className="oc-dialog-close" onClick={ev => {ev.preventDefault(); onClose();}}></a>

				<div className="oc-dialog-content">
					{children}
				</div>
			</div>
		</>,
		document.body,
	) as unknown as JSX.Element;
};

export default Dialog;

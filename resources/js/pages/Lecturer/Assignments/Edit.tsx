// Halaman ubah tugas — termasuk mengelola berkas instruksi yang sudah ada.
import { ShellProps } from '../../../components/Shared/AdminShell';
import AssignmentForm, { AssignmentDefaults, ExistingFile } from './AssignmentForm';

type OfferingInfo = {
    course: string; label: string; academicYear: string; studyProgram: string; enrolledCount: number;
};

type Assignment = AssignmentDefaults & { files: ExistingFile[] };

type Props = {
    shell: ShellProps;
    id: number;
    offeringId: number;
    offeringInfo: OfferingInfo;
    assignment: Assignment;
    urls: { index: string; show: string; update: string };
};

export default function AssignmentsEdit({ shell, offeringInfo, assignment, urls }: Props) {
    const defaults: AssignmentDefaults = {
        title: assignment.title,
        description: assignment.description,
        dueAt: assignment.dueAt,
        maxScore: assignment.maxScore,
        allowedFileTypes: assignment.allowedFileTypes,
        maxFileSizeMb: assignment.maxFileSizeMb,
        allowTextSubmission: assignment.allowTextSubmission,
        allowFileSubmission: assignment.allowFileSubmission,
        allowResubmission: assignment.allowResubmission,
        acceptLateSubmission: assignment.acceptLateSubmission,
        isPublished: assignment.isPublished,
    };

    return (
        <AssignmentForm
            shell={shell}
            pageTitle="Edit Tugas"
            heroDescription={`${offeringInfo.course} · ${offeringInfo.label}`}
            defaults={defaults}
            existingFiles={assignment.files}
            offeringInfo={offeringInfo}
            urls={{ index: urls.index, submit: urls.update, show: urls.show }}
            submitMethod="put"
        />
    );
}
